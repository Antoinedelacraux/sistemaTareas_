<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use App\Models\Audit;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\User;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Modelo para la gestión de dependencias entre tareas (Grafo Acíclico Dirigido - DAG).
 * Implementa las reglas funcionales y de negocio:
 * - RF-14: Modelar dependencias secuenciales entre tareas.
 * - RF-15: Permitir ramas de tareas en paralelo.
 * - RF-16: Habilitar dependientes cuando finalicen todas sus predecesoras (desbloqueo en cascada).
 * - RN-07: Dependencias no deben formar ciclos (detección de grafos cíclicos / DAG).
 * - RN-08: Regla AND: Una sucesora requiere que TODAS las predecesoras alcancen cierre efectivo (FINALIZADA).
 * - RN-09: Una tarea habilitada pasa de BLOQUEADA a PENDIENTE, pero no significa finalizada.
 * - RN-14: Reabrir una tarea evalúa dependientes sin borrar entregas posteriores.
 */
class TaskDependency
{
    public const TIPO_AND_FINISH_TO_START = 'AND_FINISH_TO_START';

    /**
     * Asegura que la tabla tarea_dependencias exista en la base de datos actual.
     */
    public static function ensureTable(): void
    {
        $db = Database::getConnection();
        $db->exec("
            CREATE TABLE IF NOT EXISTS tarea_dependencias (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tarea_id INTEGER NOT NULL,
                predecesora_id INTEGER NOT NULL,
                tipo_dependencia TEXT NOT NULL DEFAULT 'AND_FINISH_TO_START',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (tarea_id) REFERENCES tareas(id) ON DELETE CASCADE,
                FOREIGN KEY (predecesora_id) REFERENCES tareas(id) ON DELETE CASCADE,
                UNIQUE(tarea_id, predecesora_id)
            );
        ");
    }

    /**
     * Agrega una relación de precedencia entre dos tareas.
     * $predecesoraId debe finalizar antes de que $tareaId pueda ejecutarse.
     *
     * @param int $tareaId Tarea sucesora (dependiente)
     * @param int $predecesoraId Tarea predecesora (requisito)
     * @param array $currentUser Usuario autenticado
     * @return array Datos de la dependencia creada y estado resultante
     */
    public static function add(int $tareaId, int $predecesoraId, array $currentUser): array
    {
        self::ensureTable();

        // 1. Regla: No auto-dependencia
        if ($tareaId === $predecesoraId) {
            throw new InvalidArgumentException("Una tarea no puede depender de sí misma.", 422);
        }

        // 2. Verificar existencia de ambas tareas
        $tarea = Task::findById($tareaId);
        if (!$tarea) {
            throw new RuntimeException("La tarea sucesora no existe.", 404);
        }

        $predecesora = Task::findById($predecesoraId);
        if (!$predecesora) {
            throw new RuntimeException("La tarea predecesora no existe.", 404);
        }

        // 3. Permisos: El usuario debe poder editar la tarea sucesora
        if (!Task::canEdit($currentUser, $tarea)) {
            throw new RuntimeException("No tienes permisos para modificar las dependencias de esta tarea.", 403);
        }

        // 4. Verificar si la dependencia ya existe
        if (self::exists($tareaId, $predecesoraId)) {
            throw new RuntimeException("La relación de dependencia ya se encuentra registrada.", 409);
        }

        // 5. Regla RN-07: Comprobar que no forme un ciclo en el DAG
        if (self::wouldCreateCycle($tareaId, $predecesoraId)) {
            throw new RuntimeException(
                "No se puede agregar la dependencia: formaría un ciclo en el grafo de tareas (Regla RN-07).",
                409
            );
        }

        $db = Database::getConnection();
        $now = gmdate('Y-m-d H:i:s');

        $stmt = $db->prepare("
            INSERT INTO tarea_dependencias (
                tarea_id, predecesora_id, tipo_dependencia, created_at
            ) VALUES (
                :tarea_id, :predecesora_id, :tipo_dependencia, :created_at
            )
        ");
        $stmt->execute([
            ':tarea_id'          => $tareaId,
            ':predecesora_id'    => $predecesoraId,
            ':tipo_dependencia'  => self::TIPO_AND_FINISH_TO_START,
            ':created_at'        => $now
        ]);
        $depId = (int)$db->lastInsertId();

        // 6. Regla RN-08: Si la predecesora NO está en FINALIZADA y la tarea no está en FINALIZADA,
        // bloquear automáticamente la tarea sucesora si estaba en PENDIENTE o EN_PROCESO.
        $colBloqueada = BoardColumn::findByTipoEstado('BLOQUEADA');
        $autoBloqueada = false;

        $predFinalizada = ($predecesora['columna_tipo_estado'] ?? '') === BoardColumn::ESTADO_FINALIZADA;
        $tareaFinalizada = ($tarea['columna_tipo_estado'] ?? '') === BoardColumn::ESTADO_FINALIZADA;

        if (!$predFinalizada && !$tareaFinalizada && $colBloqueada) {
            if ((int)$tarea['columna_id'] !== (int)$colBloqueada['id']) {
                $stmtLock = $db->prepare("UPDATE tareas SET columna_id = :col_id, updated_at = :now WHERE id = :id");
                $stmtLock->execute([
                    ':col_id' => $colBloqueada['id'],
                    ':now'    => $now,
                    ':id'     => $tareaId
                ]);
                $autoBloqueada = true;

                Audit::log(
                    (int)$currentUser['id'],
                    'TAREA',
                    $tareaId,
                    'BLOQUEO_POR_DEPENDENCIA',
                    $tarea['columna_tipo_estado'],
                    'BLOQUEADA',
                    [
                        'motivo'           => 'Bloqueo automático: predecesora ' . $predecesora['codigo'] . ' aún no finalizada (RN-08)',
                        'predecesora_id'   => $predecesoraId,
                        'predecesora_cod'  => $predecesora['codigo']
                    ]
                );
            }
        }

        // Auditoría de creación de dependencia
        Audit::log(
            (int)$currentUser['id'],
            'DEPENDENCIA',
            $depId,
            'CREAR',
            null,
            'ACTIVA',
            [
                'tarea_id'        => $tareaId,
                'tarea_codigo'    => $tarea['codigo'],
                'predecesora_id'  => $predecesoraId,
                'predecesora_cod' => $predecesora['codigo']
            ]
        );

        return [
            'id'              => $depId,
            'tarea_id'        => $tareaId,
            'predecesora_id'  => $predecesoraId,
            'auto_bloqueada'  => $autoBloqueada,
            'predecesora'     => [
                'id'          => $predecesora['id'],
                'codigo'      => $predecesora['codigo'],
                'titulo'      => $predecesora['titulo'],
                'estado'      => $predecesora['columna_tipo_estado'],
                'finalizada'  => $predFinalizada
            ]
        ];
    }

    /**
     * Elimina una relación de dependencia entre dos tareas.
     */
    public static function remove(int $tareaId, int $predecesoraId, array $currentUser): bool
    {
        self::ensureTable();

        $tarea = Task::findById($tareaId);
        if (!$tarea) {
            throw new RuntimeException("La tarea no existe.", 404);
        }

        if (!Task::canEdit($currentUser, $tarea)) {
            throw new RuntimeException("No tienes permisos para modificar las dependencias de esta tarea.", 403);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM tarea_dependencias WHERE tarea_id = :t_id AND predecesora_id = :p_id");
        $stmt->execute([
            ':t_id' => $tareaId,
            ':p_id' => $predecesoraId
        ]);

        $deleted = $stmt->rowCount() > 0;
        if (!$deleted) {
            return false;
        }

        // Si la tarea estaba BLOQUEADA y ya no le quedan predecesoras pendientes, desbloquear automáticamente
        if (($tarea['columna_tipo_estado'] ?? '') === BoardColumn::ESTADO_BLOQUEADA) {
            if (!self::hasPendingPredecessors($tareaId)) {
                $colPendiente = BoardColumn::findByTipoEstado('PENDIENTE');
                if ($colPendiente) {
                    $now = gmdate('Y-m-d H:i:s');
                    $stmtUnlock = $db->prepare("UPDATE tareas SET columna_id = :col_id, updated_at = :now WHERE id = :id");
                    $stmtUnlock->execute([
                        ':col_id' => $colPendiente['id'],
                        ':now'    => $now,
                        ':id'     => $tareaId
                    ]);

                    Audit::log(
                        (int)$currentUser['id'],
                        'TAREA',
                        $tareaId,
                        'DESBLOQUEO_AUTOMATICO',
                        'BLOQUEADA',
                        'PENDIENTE',
                        ['motivo' => 'Desbloqueo tras eliminación de dependencia bloqueante']
                    );
                }
            }
        }

        Audit::log(
            (int)$currentUser['id'],
            'DEPENDENCIA',
            $tareaId,
            'ELIMINAR',
            'ACTIVA',
            null,
            ['predecesora_eliminada_id' => $predecesoraId]
        );

        return true;
    }

    /**
     * Comprueba si una relación de dependencia ya existe.
     */
    public static function exists(int $tareaId, int $predecesoraId): bool
    {
        self::ensureTable();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT 1 FROM tarea_dependencias WHERE tarea_id = :t_id AND predecesora_id = :p_id LIMIT 1");
        $stmt->execute([':t_id' => $tareaId, ':p_id' => $predecesoraId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Detección de ciclos en el DAG (Regla RN-07).
     * Si añadimos la arista ($predecesoraId -> $tareaId), habría un ciclo si ya existe
     * un camino dirigido desde $tareaId hacia $predecesoraId.
     *
     * @param int $tareaId Tarea sucesora candidata
     * @param int $predecesoraId Tarea predecesora candidata
     * @return bool True si se formaría un ciclo
     */
    public static function wouldCreateCycle(int $tareaId, int $predecesoraId): bool
    {
        self::ensureTable();
        $visited = [];
        $queue = [$tareaId];

        while (!empty($queue)) {
            $curr = array_shift($queue);
            if ($curr === $predecesoraId) {
                return true; // Existe un camino de tareaId a predecesoraId, formarían un ciclo
            }

            if (isset($visited[$curr])) {
                continue;
            }
            $visited[$curr] = true;

            // Obtener todas las sucesoras directas de $curr
            $succIds = self::getSuccessorIds($curr);
            foreach ($succIds as $sId) {
                if (!isset($visited[$sId])) {
                    $queue[] = $sId;
                }
            }
        }

        return false;
    }

    /**
     * Obtiene los IDs de las tareas sucesoras directas de una tarea dada.
     *
     * @param int $tareaId
     * @return array
     */
    public static function getSuccessorIds(int $tareaId): array
    {
        self::ensureTable();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT tarea_id FROM tarea_dependencias WHERE predecesora_id = :p_id");
        $stmt->execute([':p_id' => $tareaId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Obtiene los IDs de las tareas predecesoras directas de una tarea dada.
     *
     * @param int $tareaId
     * @return array
     */
    public static function getPredecessorIds(int $tareaId): array
    {
        self::ensureTable();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT predecesora_id FROM tarea_dependencias WHERE tarea_id = :t_id");
        $stmt->execute([':t_id' => $tareaId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Comprueba si una tarea tiene al menos una predecesora que NO ha alcanzado el estado FINALIZADA.
     *
     * @param int $tareaId
     * @return bool True si hay dependencias pendientes
     */
    public static function hasPendingPredecessors(int $tareaId): bool
    {
        self::ensureTable();
        $db = Database::getConnection();
        $sql = "
            SELECT 1
            FROM tarea_dependencias td
            JOIN tareas tp ON tp.id = td.predecesora_id
            JOIN tableros_columnas tc ON tc.id = tp.columna_id
            WHERE td.tarea_id = :t_id
              AND tc.tipo_estado != 'FINALIZADA'
            LIMIT 1
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([':t_id' => $tareaId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Obtiene el listado de predecesoras pendientes que impiden el avance de la tarea.
     */
    public static function getPendingPredecessors(int $tareaId): array
    {
        self::ensureTable();
        $db = Database::getConnection();
        $sql = "
            SELECT tp.id, tp.codigo, tp.titulo, tc.nombre AS columna_nombre, tc.tipo_estado
            FROM tarea_dependencias td
            JOIN tareas tp ON tp.id = td.predecesora_id
            JOIN tableros_columnas tc ON tc.id = tp.columna_id
            WHERE td.tarea_id = :t_id
              AND tc.tipo_estado != 'FINALIZADA'
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([':t_id' => $tareaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna la estructura completa de dependencias de una tarea (predecesoras y sucesoras).
     */
    public static function getByTask(int $tareaId, ?array $currentUser = null): array
    {
        self::ensureTable();
        $db = Database::getConnection();

        // 1. Tareas predecesoras (requisitos para ejecutar $tareaId)
        $sqlPred = "
            SELECT
                td.id AS dependencia_id,
                td.created_at AS dependencia_creada_en,
                tp.id,
                tp.codigo,
                tp.titulo,
                tp.tipo_tarea,
                tp.prioridad,
                tc.id AS columna_id,
                tc.nombre AS columna_nombre,
                tc.tipo_estado,
                tc.color_hex AS columna_color_hex,
                u.nombre AS responsable_nombre,
                CASE WHEN tc.tipo_estado = 'FINALIZADA' THEN 1 ELSE 0 END AS finalizada
            FROM tarea_dependencias td
            JOIN tareas tp ON tp.id = td.predecesora_id
            JOIN tableros_columnas tc ON tc.id = tp.columna_id
            LEFT JOIN usuarios u ON u.id = tp.responsable_id
            WHERE td.tarea_id = :t_id
            ORDER BY tp.id ASC
        ";
        $stmtPred = $db->prepare($sqlPred);
        $stmtPred->execute([':t_id' => $tareaId]);
        $predecesoras = $stmtPred->fetchAll(PDO::FETCH_ASSOC);

        // 2. Tareas sucesoras (tareas que esperan por $tareaId)
        $sqlSucc = "
            SELECT
                td.id AS dependencia_id,
                td.created_at AS dependencia_creada_en,
                ts.id,
                ts.codigo,
                ts.titulo,
                ts.tipo_tarea,
                ts.prioridad,
                tc.id AS columna_id,
                tc.nombre AS columna_nombre,
                tc.tipo_estado,
                tc.color_hex AS columna_color_hex,
                u.nombre AS responsable_nombre,
                CASE WHEN tc.tipo_estado = 'FINALIZADA' THEN 1 ELSE 0 END AS finalizada
            FROM tarea_dependencias td
            JOIN tareas ts ON ts.id = td.tarea_id
            JOIN tableros_columnas tc ON tc.id = ts.columna_id
            LEFT JOIN usuarios u ON u.id = ts.responsable_id
            WHERE td.predecesora_id = :t_id
            ORDER BY ts.id ASC
        ";
        $stmtSucc = $db->prepare($sqlSucc);
        $stmtSucc->execute([':t_id' => $tareaId]);
        $sucesoras = $stmtSucc->fetchAll(PDO::FETCH_ASSOC);

        $totalPred = count($predecesoras);
        $completadasPred = count(array_filter($predecesoras, fn($p) => (int)$p['finalizada'] === 1));
        $bloqueadaPorDep = ($totalPred > 0 && $completadasPred < $totalPred);

        return [
            'tarea_id'                   => $tareaId,
            'total_predecesoras'         => $totalPred,
            'predecesoras_completadas'   => $completadasPred,
            'bloqueada_por_dependencias' => $bloqueadaPorDep,
            'predecesoras'               => $predecesoras,
            'sucesoras'                  => $sucesoras
        ];
    }

    /**
     * Disparador en cascada (Regla RF-16, RN-08):
     * Se ejecuta cuando una tarea ($completedTaskId) pasa a estado FINALIZADA.
     * Evalúa a todas sus sucesoras; si TODAS sus predecesoras están finalizadas,
     * desbloquea la sucesora pasándola automáticamente a PENDIENTE.
     *
     * @param int $completedTaskId ID de la tarea recién finalizada
     * @param array $currentUser Usuario que ejecutó o resolvió la finalización
     * @return array Lista de IDs de tareas que fueron desbloqueadas automáticamente
     */
    public static function onTaskCompleted(int $completedTaskId, array $currentUser): array
    {
        self::ensureTable();
        $db = Database::getConnection();

        // Buscar todas las tareas que dependen de esta tarea
        $successorIds = self::getSuccessorIds($completedTaskId);
        if (empty($successorIds)) {
            return [];
        }

        $colPendiente = BoardColumn::findByTipoEstado('PENDIENTE');
        if (!$colPendiente) {
            return [];
        }

        $desbloqueadas = [];
        $now = gmdate('Y-m-d H:i:s');
        $userId = (int)($currentUser['id'] ?? 1);

        foreach ($successorIds as $succId) {
            $succTask = Task::findById($succId);
            if (!$succTask) {
                continue;
            }

            // Si la sucesora ya está finalizada, no alterarla
            if (($succTask['columna_tipo_estado'] ?? '') === BoardColumn::ESTADO_FINALIZADA) {
                continue;
            }

            // Regla AND (RN-08): ¿Todas sus predecesoras están finalizadas?
            if (!self::hasPendingPredecessors($succId)) {
                // Si estaba en estado BLOQUEADA, desbloquearla hacia PENDIENTE (RN-09)
                if (($succTask['columna_tipo_estado'] ?? '') === BoardColumn::ESTADO_BLOQUEADA) {
                    $stmtUnlock = $db->prepare("UPDATE tareas SET columna_id = :col_id, updated_at = :now WHERE id = :id");
                    $stmtUnlock->execute([
                        ':col_id' => $colPendiente['id'],
                        ':now'    => $now,
                        ':id'     => $succId
                    ]);

                    Audit::log(
                        $userId,
                        'TAREA',
                        $succId,
                        'DESBLOQUEO_AUTOMATICO_CASCADA',
                        'BLOQUEADA',
                        'PENDIENTE',
                        [
                            'motivo'            => 'Todas las tareas predecesoras alcanzaron estado FINALIZADA (RN-08, RN-16)',
                            'gatillada_por_id'  => $completedTaskId
                        ]
                    );

                    $desbloqueadas[] = $succId;
                }
            }
        }

        return $desbloqueadas;
    }

    /**
     * Disparador ante reapertura de tarea (Regla RN-14):
     * Si una tarea sale del estado FINALIZADA (o es devuelta en revisión),
     * sus sucesoras que estaban en PENDIENTE o EN_PROCESO vuelven a bloquearse (BLOQUEADA).
     *
     * @param int $reopenedTaskId ID de la tarea reabierta
     * @param array $currentUser Usuario
     * @return array IDs de tareas re-bloqueadas
     */
    public static function onTaskReopened(int $reopenedTaskId, array $currentUser): array
    {
        self::ensureTable();
        $db = Database::getConnection();

        $successorIds = self::getSuccessorIds($reopenedTaskId);
        if (empty($successorIds)) {
            return [];
        }

        $colBloqueada = BoardColumn::findByTipoEstado('BLOQUEADA');
        if (!$colBloqueada) {
            return [];
        }

        $rebloqueadas = [];
        $now = gmdate('Y-m-d H:i:s');
        $userId = (int)($currentUser['id'] ?? 1);

        foreach ($successorIds as $succId) {
            $succTask = Task::findById($succId);
            if (!$succTask) {
                continue;
            }

            // No re-bloquear tareas que ya están finalizadas (RN-14: sin borrar entregas posteriores automáticamente)
            if (($succTask['columna_tipo_estado'] ?? '') === BoardColumn::ESTADO_FINALIZADA) {
                continue;
            }

            // Si ahora tiene predecesoras pendientes y no está en BLOQUEADA, bloquear
            if (self::hasPendingPredecessors($succId) && ($succTask['columna_tipo_estado'] ?? '') !== BoardColumn::ESTADO_BLOQUEADA) {
                $stmtLock = $db->prepare("UPDATE tareas SET columna_id = :col_id, updated_at = :now WHERE id = :id");
                $stmtLock->execute([
                    ':col_id' => $colBloqueada['id'],
                    ':now'    => $now,
                    ':id'     => $succId
                ]);

                Audit::log(
                    $userId,
                    'TAREA',
                    $succId,
                    'REBLOQUEO_POR_REAPERTURA',
                    $succTask['columna_tipo_estado'],
                    'BLOQUEADA',
                    [
                        'motivo'            => 'Reapertura de predecesora ' . $reopenedTaskId . ' (RN-14)',
                        'gatillada_por_id'  => $reopenedTaskId
                    ]
                );

                $rebloqueadas[] = $succId;
            }
        }

        return $rebloqueadas;
    }
}
