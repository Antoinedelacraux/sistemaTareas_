<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

/**
 * Modelo de Auditoría inmutable de eventos del sistema (RF-18, RN-13).
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
}
