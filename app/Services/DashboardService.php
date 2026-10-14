<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Models\BoardColumn;
use App\Models\User;
use PDO;

/**
 * Servicio de Inteligencia Operativa y Métricas de Dashboard (RF-19).
 * Genera KPIs de avance, distribución por estado, detección de cuellos de botella
 * y balance de carga de trabajo según el rol y ámbito del usuario solicitante.
 */
class DashboardService
{
    /**
     * Obtiene el conjunto completo de métricas operativas.
     *
     * @param array $currentUser Usuario autenticado
     * @param string $scope 'general' | 'area' | 'personal'
     * @return array
     */
    public static function getMetrics(array $currentUser, string $scope = 'area'): array
    {
        $db = Database::getConnection();
        $isJefe = in_array($currentUser['rol'] ?? '', [User::ROL_JEFE_PLANEAMIENTO, User::ROL_JEFE_PERSONAL], true);
        $userId = (int)$currentUser['id'];

        // Determinar alcance efectivo de la consulta
        // Si es asistente, forzar filtro a sus propias tareas para evitar fuga de información
        $whereSql = "1=1";
        $params = [];

        if (!$isJefe) {
            if ($scope === 'personal') {
                $whereSql .= " AND t.tipo_tarea = 'PERSONAL' AND t.responsable_id = :u_id";
                $params[':u_id'] = $userId;
            } else {
                // Por defecto o 'area': tareas oficiales asignadas al asistente
                $whereSql .= " AND t.tipo_tarea = 'AREA' AND t.responsable_id = :u_id";
                $params[':u_id'] = $userId;
            }
        } else {
            // Jefatura:
            if ($scope === 'personal') {
                $whereSql .= " AND t.tipo_tarea = 'PERSONAL' AND t.responsable_id = :u_id";
                $params[':u_id'] = $userId;
            } else {
                // Áreas oficiales de todo el equipo
                $whereSql .= " AND t.tipo_tarea = 'AREA'";
            }
        }

        // 1. Resumen por Tipo de Estado Funcional
        $sqlEstados = "
            SELECT 
                tc.tipo_estado,
                COUNT(t.id) AS total
            FROM tableros_columnas tc
            LEFT JOIN tareas t ON t.columna_id = tc.id AND {$whereSql}
            WHERE tc.activo = 1
            GROUP BY tc.tipo_estado
        ";
        $stmtEstados = $db->prepare($sqlEstados);
        $stmtEstados->execute($params);
        $estadosRows = $stmtEstados->fetchAll(PDO::FETCH_KEY_PAIR);

        $pendientes = (int)($estadosRows[BoardColumn::ESTADO_PENDIENTE] ?? 0);
        $enProceso  = (int)($estadosRows[BoardColumn::ESTADO_EN_PROCESO] ?? 0);
        $bloqueadas = (int)($estadosRows[BoardColumn::ESTADO_BLOQUEADA] ?? 0);
        $enRevision = (int)($estadosRows[BoardColumn::ESTADO_EN_REVISION] ?? 0);
        $finalizadas= (int)($estadosRows[BoardColumn::ESTADO_FINALIZADA] ?? 0);

        $totalTareas = $pendientes + $enProceso + $bloqueadas + $enRevision + $finalizadas;
        $tasaCumplimiento = $totalTareas > 0 ? round(($finalizadas / $totalTareas) * 100, 1) : 0.0;

        // 2. Tareas vencidas y próximas a vencer (Alertas de Plazo)
        $now = gmdate('Y-m-d H:i:s');
        $in7Days = gmdate('Y-m-d H:i:s', time() + (7 * 86400));

        $sqlVencidas = "
            SELECT COUNT(t.id) 
            FROM tareas t
            JOIN tableros_columnas tc ON tc.id = t.columna_id
            WHERE {$whereSql}
              AND tc.tipo_estado != 'FINALIZADA'
              AND t.fecha_limite IS NOT NULL
              AND t.fecha_limite < :now
        ";
        $stmtVencidas = $db->prepare($sqlVencidas);
        $stmtVencidas->execute(array_merge($params, [':now' => $now]));
        $totalVencidas = (int)$stmtVencidas->fetchColumn();

        $sqlPorVencer = "
            SELECT COUNT(t.id) 
            FROM tareas t
            JOIN tableros_columnas tc ON tc.id = t.columna_id
            WHERE {$whereSql}
              AND tc.tipo_estado != 'FINALIZADA'
              AND t.fecha_limite IS NOT NULL
              AND t.fecha_limite >= :now
              AND t.fecha_limite <= :in7days
        ";
        $stmtPorVencer = $db->prepare($sqlPorVencer);
        $stmtPorVencer->execute(array_merge($params, [':now' => $now, ':in7days' => $in7Days]));
        $totalPorVencer = (int)$stmtPorVencer->fetchColumn();

        // 3. Distribución por Prioridad
        $sqlPrioridad = "
            SELECT 
                t.prioridad,
                COUNT(t.id) AS total
            FROM tareas t
            JOIN tableros_columnas tc ON tc.id = t.columna_id
            WHERE {$whereSql}
              AND tc.tipo_estado != 'FINALIZADA'
            GROUP BY t.prioridad
        ";
        $stmtPrioridad = $db->prepare($sqlPrioridad);
        $stmtPrioridad->execute($params);
        $prioridadRows = $stmtPrioridad->fetchAll(PDO::FETCH_KEY_PAIR);

        $prioridades = [
            'ALTA'  => (int)($prioridadRows['ALTA'] ?? 0),
            'MEDIA' => (int)($prioridadRows['MEDIA'] ?? 0),
            'BAJA'  => (int)($prioridadRows['BAJA'] ?? 0)
        ];

        // 4. Aprobaciones pendientes (si es jefe o solicitante)
        $totalAprobacionesPendientes = 0;
        try {
            if ($isJefe) {
                $stmtAprob = $db->query("SELECT COUNT(*) FROM tarea_aprobaciones WHERE estado = 'PENDIENTE'");
                $totalAprobacionesPendientes = (int)$stmtAprob->fetchColumn();
            } else {
                $stmtAprob = $db->prepare("
                    SELECT COUNT(a.id) 
                    FROM tarea_aprobaciones a
                    JOIN tareas t ON t.id = a.tarea_id
                    WHERE a.estado = 'PENDIENTE' AND t.responsable_id = :u_id
                ");
                $stmtAprob->execute([':u_id' => $userId]);
                $totalAprobacionesPendientes = (int)$stmtAprob->fetchColumn();
            }
        } catch (\Throwable $e) {}

        // 5. Balance de carga por Responsable (solo disponible para Jefatura)
        $equipoCarga = [];
        if ($isJefe && $scope !== 'personal') {
            $sqlEquipo = "
                SELECT 
                    u.id AS usuario_id,
                    u.nombre,
                    u.cargo,
                    u.rol,
                    COUNT(t.id) AS total_asignadas,
                    SUM(CASE WHEN tc.tipo_estado = 'EN_PROCESO' THEN 1 ELSE 0 END) AS en_proceso,
                    SUM(CASE WHEN tc.tipo_estado = 'BLOQUEADA' THEN 1 ELSE 0 END) AS bloqueadas,
                    SUM(CASE WHEN tc.tipo_estado = 'EN_REVISION' THEN 1 ELSE 0 END) AS en_revision,
                    SUM(CASE WHEN tc.tipo_estado = 'FINALIZADA' THEN 1 ELSE 0 END) AS finalizadas
                FROM usuarios u
                LEFT JOIN tareas t ON t.responsable_id = u.id AND t.tipo_tarea = 'AREA'
                LEFT JOIN tableros_columnas tc ON tc.id = t.columna_id
                WHERE u.activo = 1
                GROUP BY u.id, u.nombre, u.cargo, u.rol
                ORDER BY total_asignadas DESC
            ";
            $stmtEquipo = $db->query($sqlEquipo);
            $rowsEquipo = $stmtEquipo->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rowsEquipo as $row) {
                $tot = (int)$row['total_asignadas'];
                $fin = (int)$row['finalizadas'];
                $tasa = $tot > 0 ? round(($fin / $tot) * 100, 1) : 0.0;

                $equipoCarga[] = [
                    'usuario_id'       => (int)$row['usuario_id'],
                    'nombre'           => $row['nombre'],
                    'cargo'            => $row['cargo'],
                    'rol'              => $row['rol'],
                    'total_asignadas'  => $tot,
                    'en_proceso'       => (int)$row['en_proceso'],
                    'bloqueadas'       => (int)$row['bloqueadas'],
                    'en_revision'      => (int)$row['en_revision'],
                    'finalizadas'      => $fin,
                    'tasa_cumplimiento'=> $tasa
                ];
            }
        }

        return [
            'alcance'                => $scope,
            'es_jefe'                => $isJefe,
            'resumen_estados'        => [
                'total'               => $totalTareas,
                'pendientes'          => $pendientes,
                'en_proceso'          => $enProceso,
                'bloqueadas'          => $bloqueadas,
                'en_revision'         => $enRevision,
                'finalizadas'         => $finalizadas,
                'tasa_cumplimiento'   => $tasaCumplimiento
            ],
            'alertas_plazos'         => [
                'vencidas'            => $totalVencidas,
                'por_vencer_7_dias'   => $totalPorVencer
            ],
            'prioridades_activas'    => $prioridades,
            'aprobaciones_pendientes'=> $totalAprobacionesPendientes,
            'balance_equipo'         => $equipoCarga,
            'generado_en'            => gmdate('Y-m-d H:i:s')
        ];
    }
}
