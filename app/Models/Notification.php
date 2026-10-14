<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;
use Throwable;

/**
 * Modelo para Notificaciones Internas e Idempotentes (Fase 1: MVP-08).
 * Requisitos cubiertos:
 * - RF-17: Generar notificaciones internas de eventos relevantes.
 * - RN-15: Eventos automatizados idempotentes; prevención de duplicados vía idempotency_key.
 */
class Notification
{
    public const EVENTO_TAREA_ASIGNADA         = 'TAREA_ASIGNADA';
    public const EVENTO_APROBACION_SOLICITADA   = 'APROBACION_SOLICITADA';
    public const EVENTO_TAREA_APROBADA         = 'TAREA_APROBADA';
    public const EVENTO_TAREA_DEVUELTA          = 'TAREA_DEVUELTA';
    public const EVENTO_TAREA_DESBLOQUEADA      = 'TAREA_DESBLOQUEADA';
    public const EVENTO_NUEVO_COMENTARIO        = 'NUEVO_COMENTARIO';

    /**
     * Asegura la existencia de la tabla en el entorno actual.
     */
    public static function ensureTable(): void
    {
        $db = Database::getConnection();
        $db->exec("
            CREATE TABLE IF NOT EXISTS notificaciones (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                usuario_id INTEGER NOT NULL,
                tipo_evento TEXT NOT NULL,
                titulo TEXT NOT NULL,
                mensaje TEXT NOT NULL,
                entidad_tipo TEXT NOT NULL DEFAULT 'TAREA',
                entidad_id INTEGER NOT NULL,
                enlace TEXT,
                leida INTEGER NOT NULL DEFAULT 0,
                idempotency_key TEXT UNIQUE,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                leida_at TEXT,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
            );
            CREATE INDEX IF NOT EXISTS idx_notif_usuario_leida ON notificaciones(usuario_id, leida);
        ");
    }

    /**
     * Crea una notificación interna garantizando idempotencia estricta (RN-15).
     * Si la clave de idempotencia ya fue procesada, se omite silenciosamente sin duplicar el registro.
     *
     * @param int $usuarioId Destinatario de la alerta
     * @param string $tipoEvento Tipo de evento
     * @param string $titulo Título de la notificación
     * @param string $mensaje Contenido descriptivo
     * @param string $entidadTipo 'TAREA', 'APROBACION', etc.
     * @param int $entidadId ID de la entidad
     * @param string|null $idempotencyKey Clave única de idempotencia
     * @param string|null $enlace Ruta o código para navegación rápida
     * @return array|null Datos de la notificación creada, o null si ya existía (idempotente)
     */
    public static function create(
        int $usuarioId,
        string $tipoEvento,
        string $titulo,
        string $mensaje,
        string $entidadTipo,
        int $entidadId,
        ?string $idempotencyKey = null,
        ?string $enlace = null
    ): ?array {
        self::ensureTable();
        $db = Database::getConnection();

        // Si no se especifica clave, generar una basada en el evento y entidad
        if ($idempotencyKey === null) {
            $idempotencyKey = sha1("{$usuarioId}:{$tipoEvento}:{$entidadTipo}:{$entidadId}:" . microtime(true));
        } else {
            // Verificar si ya existe previamente (garantía idempotente activa)
            $stmtCheck = $db->prepare("SELECT id FROM notificaciones WHERE idempotency_key = :key LIMIT 1");
            $stmtCheck->execute([':key' => $idempotencyKey]);
            $existingId = $stmtCheck->fetchColumn();
            if ($existingId) {
                return self::findById((int)$existingId);
            }
        }

        $now = gmdate('Y-m-d H:i:s');

        try {
            $stmt = $db->prepare("
                INSERT INTO notificaciones (
                    usuario_id, tipo_evento, titulo, mensaje, entidad_tipo,
                    entidad_id, enlace, leida, idempotency_key, created_at
                ) VALUES (
                    :usuario_id, :tipo_evento, :titulo, :mensaje, :entidad_tipo,
                    :entidad_id, :enlace, 0, :idempotency_key, :created_at
                )
            ");
            $stmt->execute([
                ':usuario_id'       => $usuarioId,
                ':tipo_evento'      => $tipoEvento,
                ':titulo'           => $titulo,
                ':mensaje'          => $mensaje,
                ':entidad_tipo'     => $entidadTipo,
                ':entidad_id'       => $entidadId,
                ':enlace'           => $enlace,
                ':idempotency_key'  => $idempotencyKey,
                ':created_at'       => $now
            ]);

            $id = (int)$db->lastInsertId();
            return self::findById($id);
        } catch (Throwable $e) {
            // Capturar violación de unicidad por carrera concurrente
            if (str_contains($e->getMessage(), 'UNIQUE') || str_contains($e->getMessage(), '23000')) {
                $stmtCheck = $db->prepare("SELECT id FROM notificaciones WHERE idempotency_key = :key LIMIT 1");
                $stmtCheck->execute([':key' => $idempotencyKey]);
                $existingId = $stmtCheck->fetchColumn();
                return $existingId ? self::findById((int)$existingId) : null;
            }
            throw $e;
        }
    }

    /**
     * Busca una notificación por su ID.
     */
    public static function findById(int $id): ?array
    {
        self::ensureTable();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM notificaciones WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Lista notificaciones para un usuario específico con soporte de paginación/límite y filtro por estado leído.
     */
    public static function listByUser(int $usuarioId, bool $soloNoLeidas = false, int $limit = 50): array
    {
        self::ensureTable();
        $db = Database::getConnection();

        $sql = "SELECT * FROM notificaciones WHERE usuario_id = :u_id";
        if ($soloNoLeidas) {
            $sql .= " AND leida = 0";
        }
        $sql .= " ORDER BY id DESC LIMIT :limit";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':u_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min($limit, 100)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta cuántas notificaciones no leídas tiene el usuario.
     */
    public static function countUnread(int $usuarioId): int
    {
        self::ensureTable();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id = :u_id AND leida = 0");
        $stmt->execute([':u_id' => $usuarioId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Marca una notificación como leída con validación de pertenencia (anti-IDOR).
     */
    public static function markAsRead(int $id, int $usuarioId): bool
    {
        self::ensureTable();
        $db = Database::getConnection();

        $now = gmdate('Y-m-d H:i:s');
        $stmt = $db->prepare("
            UPDATE notificaciones
            SET leida = 1, leida_at = :now
            WHERE id = :id AND usuario_id = :u_id
        ");
        $stmt->execute([
            ':now'  => $now,
            ':id'   => $id,
            ':u_id' => $usuarioId
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Marca todas las notificaciones pendientes del usuario como leídas.
     */
    public static function markAllAsRead(int $usuarioId): int
    {
        self::ensureTable();
        $db = Database::getConnection();

        $now = gmdate('Y-m-d H:i:s');
        $stmt = $db->prepare("
            UPDATE notificaciones
            SET leida = 1, leida_at = :now
            WHERE usuario_id = :u_id AND leida = 0
        ");
        $stmt->execute([
            ':now'  => $now,
            ':u_id' => $usuarioId
        ]);

        return $stmt->rowCount();
    }
}
