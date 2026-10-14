<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

/**
 * Modelo de Auditoría inmutable de eventos del sistema (RF-18, RN-13).
 * Registra y permite auditar cualquier transición de estado, reasignación,
 * aprobación, eliminación o cambio crítico.
 */
class Audit
{
    /**
     * Registra un evento en la bitácora inmutable de auditoría.
     */
    public static function log(
        ?int $userId,
        string $entityType,
        int $entityId,
        string $action,
        ?string $prevStatus = null,
        ?string $newStatus = null,
        ?array $details = null,
        ?string $ip = null
    ): void {
        $db = Database::getConnection();
        
        $sql = "INSERT INTO auditoria_eventos 
                (usuario_id, entidad_tipo, entidad_id, accion, estado_anterior, estado_nuevo, detalles_json, ip_address, created_at)
                VALUES 
                (:usuario_id, :entidad_tipo, :entidad_id, :accion, :estado_anterior, :estado_nuevo, :detalles_json, :ip_address, :created_at)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':usuario_id'     => $userId,
            ':entidad_tipo'   => $entityType,
            ':entidad_id'     => $entityId,
            ':accion'         => $action,
            ':estado_anterior'=> $prevStatus,
            ':estado_nuevo'   => $newStatus,
            ':detalles_json'  => $details !== null ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            ':ip_address'     => $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            ':created_at'     => gmdate('Y-m-d H:i:s')
        ]);
    }

    /**
     * Obtiene el historial cronológico completo de una tarea específica (RF-18).
     * Incluye eventos directos de la tarea, aprobaciones vinculadas y dependencias.
     *
     * @param int $tareaId
     * @param int $limit
     * @return array
     */
    public static function getByTask(int $tareaId, int $limit = 100): array
    {
        $db = Database::getConnection();

        // Buscar eventos donde la entidad directa es la tarea o dependencias,
        // o aprobaciones asociadas a la tarea en detalles_json
        $sql = "
            SELECT 
                a.*,
                u.nombre AS usuario_nombre,
                u.email AS usuario_email,
                u.rol AS usuario_rol,
                u.cargo AS usuario_cargo
            FROM auditoria_eventos a
            LEFT JOIN usuarios u ON u.id = a.usuario_id
            WHERE (
                (a.entidad_tipo = 'TAREA' AND a.entidad_id = :t_id1)
                OR (a.entidad_tipo = 'DEPENDENCIA' AND a.entidad_id = :t_id2)
                OR (a.entidad_tipo = 'APROBACION' AND a.detalles_json LIKE :pattern)
            )
            ORDER BY a.id ASC
            LIMIT :limit
        ";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':t_id1', $tareaId, PDO::PARAM_INT);
        $stmt->bindValue(':t_id2', $tareaId, PDO::PARAM_INT);
        $stmt->bindValue(':pattern', '%"tarea_id":' . $tareaId . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', max(1, min($limit, 200)), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([self::class, 'format'], $rows);
    }

    /**
     * Obtiene los eventos recientes del sistema para supervisión y trazabilidad (RF-18).
     *
     * @param int $limit
     * @param string|null $entityType
     * @param int|null $userId
     * @return array
     */
    public static function getRecent(int $limit = 50, ?string $entityType = null, ?int $userId = null): array
    {
        $db = Database::getConnection();

        $sql = "
            SELECT 
                a.*,
                u.nombre AS usuario_nombre,
                u.email AS usuario_email,
                u.rol AS usuario_rol,
                u.cargo AS usuario_cargo
            FROM auditoria_eventos a
            LEFT JOIN usuarios u ON u.id = a.usuario_id
            WHERE 1=1
        ";

        $params = [];

        if ($entityType !== null && $entityType !== '') {
            $sql .= " AND a.entidad_tipo = :entityType";
            $params[':entityType'] = $entityType;
        }

        if ($userId !== null && $userId > 0) {
            $sql .= " AND a.usuario_id = :userId";
            $params[':userId'] = $userId;
        }

        $sql .= " ORDER BY a.id DESC LIMIT :limit";

        $stmt = $db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', max(1, min($limit, 100)), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([self::class, 'format'], $rows);
    }

    /**
     * Formatea un registro de auditoría para presentación estándar JSON.
     */
    public static function format(array $row): array
    {
        $details = null;
        if (!empty($row['detalles_json'])) {
            $decoded = json_decode($row['detalles_json'], true);
            if (is_array($decoded)) {
                $details = $decoded;
            }
        }

        return [
            'id'              => (int)$row['id'],
            'usuario_id'      => $row['usuario_id'] !== null ? (int)$row['usuario_id'] : null,
            'usuario_nombre'  => $row['usuario_nombre'] ?? 'Sistema / Automatización',
            'usuario_email'   => $row['usuario_email'] ?? null,
            'usuario_rol'     => $row['usuario_rol'] ?? null,
            'usuario_cargo'   => $row['usuario_cargo'] ?? null,
            'entidad_tipo'    => $row['entidad_tipo'],
            'entidad_id'      => (int)$row['entidad_id'],
            'accion'          => $row['accion'],
            'estado_anterior' => $row['estado_anterior'],
            'estado_nuevo'    => $row['estado_nuevo'],
            'detalles'        => $details,
            'ip_address'      => $row['ip_address'],
            'created_at'      => $row['created_at']
        ];
    }
}
