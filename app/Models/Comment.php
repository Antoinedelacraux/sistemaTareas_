<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

/**
 * Modelo de Comentarios y Evidencias de Tareas (RF-10, RN-02).
 */
class Comment
{
    /**
     * Lista todos los comentarios y evidencias de una tarea en orden cronológico.
     */
    public static function listByTaskId(int $taskId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT 
                c.*,
                u.nombre AS usuario_nombre,
                u.email AS usuario_email,
                u.rol AS usuario_rol
            FROM tarea_comentarios c
            INNER JOIN usuarios u ON c.usuario_id = u.id
            WHERE c.tarea_id = :tarea_id
            ORDER BY c.created_at ASC, c.id ASC
        ");
        $stmt->execute([':tarea_id' => $taskId]);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => self::format($row), $rows);
    }

    /**
     * Busca un comentario por su ID.
     */
    public static function findById(int $id): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT 
                c.*,
                u.nombre AS usuario_nombre,
                u.email AS usuario_email,
                u.rol AS usuario_rol
            FROM tarea_comentarios c
            INNER JOIN usuarios u ON c.usuario_id = u.id
            WHERE c.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? self::format($row) : null;
    }

    /**
     * Registra un nuevo comentario o evidencia adjunta en una tarea.
     *
     * @param int $taskId
     * @param int $usuarioId
     * @param string $comentario
     * @param bool $esEvidencia
     * @param array|null $archivo ['nombre_original', 'ruta_hash', 'mime', 'size']
     * @return array
     */
    public static function create(
        int $taskId,
        int $usuarioId,
        string $comentario,
        bool $esEvidencia = false,
        ?array $archivo = null
    ): array {
        $db = Database::getConnection();
        $now = gmdate('Y-m-d H:i:s');

        $stmt = $db->prepare("
            INSERT INTO tarea_comentarios (
                tarea_id, usuario_id, comentario, es_evidencia,
                archivo_nombre_original, archivo_ruta_hash, archivo_mime, archivo_size_bytes,
                created_at
            ) VALUES (
                :tarea_id, :usuario_id, :comentario, :es_evidencia,
                :archivo_nombre_original, :archivo_ruta_hash, :archivo_mime, :archivo_size_bytes,
                :created_at
            )
        ");

        $stmt->execute([
            ':tarea_id'                => $taskId,
            ':usuario_id'              => $usuarioId,
            ':comentario'              => trim($comentario),
            ':es_evidencia'            => $esEvidencia ? 1 : 0,
            ':archivo_nombre_original' => $archivo['nombre_original'] ?? null,
            ':archivo_ruta_hash'       => $archivo['ruta_hash'] ?? null,
            ':archivo_mime'            => $archivo['mime'] ?? null,
            ':archivo_size_bytes'      => isset($archivo['size']) ? (int)$archivo['size'] : null,
            ':created_at'              => $now
        ]);

        $newId = (int)$db->lastInsertId();

        // Registrar en auditoría
        Audit::log(
            $usuarioId,
            'TAREA',
            $taskId,
            $esEvidencia ? 'EVIDENCIA_REGISTRADA' : 'COMENTARIO_AGREGADO',
            null,
            null,
            [
                'comentario_id' => $newId,
                'es_evidencia'  => $esEvidencia,
                'tiene_archivo' => !empty($archivo['ruta_hash'])
            ]
        );

        $comentarioObj = self::findById($newId);

        // Disparo de notificación automática (MVP-08 / RF-17 / RN-15)
        try {
            if (class_exists(\App\Services\AutomationEngine::class)) {
                $task = Task::findById($taskId);
                $autor = User::findById($usuarioId);
                if ($task && $autor) {
                    \App\Services\AutomationEngine::notifyNewComment($task, $comentarioObj, $autor);
                }
            }
        } catch (\Throwable $e) {}

        return $comentarioObj;
    }

    /**
     * Formatea un registro de comentario para salida estándar JSON.
     */
    public static function format(array $row): array
    {
        $hasFile = !empty($row['archivo_ruta_hash']);

        return [
            'id'             => (int)$row['id'],
            'tarea_id'       => (int)$row['tarea_id'],
            'usuario_id'     => (int)$row['usuario_id'],
            'usuario_nombre' => $row['usuario_nombre'] ?? null,
            'usuario_email'  => $row['usuario_email'] ?? null,
            'usuario_rol'    => $row['usuario_rol'] ?? null,
            'comentario'     => $row['comentario'],
            'es_evidencia'   => (bool)$row['es_evidencia'],
            'adjunto'        => $hasFile ? [
                'nombre_original' => $row['archivo_nombre_original'],
                'mime'            => $row['archivo_mime'],
                'size_bytes'      => (int)$row['archivo_size_bytes'],
                'descarga_url'    => "/api/comentarios/{$row['id']}/archivo"
            ] : null,
            'created_at'     => $row['created_at']
        ];
    }
}
