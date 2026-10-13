<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

/**
 * Modelo de Subtarea (Checklist de avance dentro de una tarea).
 * Cubre: RF-10.
 */
class Subtask
{
    /**
     * Lista todas las subtareas asociadas a una tarea específica, ordenadas por su campo orden.
     */
    public static function listByTaskId(int $taskId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT id, tarea_id, titulo, completada, orden, created_at, updated_at 
            FROM subtareas 
            WHERE tarea_id = :tarea_id 
            ORDER BY orden ASC, id ASC
        ");
        $stmt->execute([':tarea_id' => $taskId]);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => self::format($row), $rows);
    }

    /**
     * Busca una subtarea por su ID.
     */
    public static function findById(int $id): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT id, tarea_id, titulo, completada, orden, created_at, updated_at 
            FROM subtareas 
            WHERE id = :id 
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? self::format($row) : null;
    }

    /**
     * Crea una nueva subtarea dentro de una tarea existente.
     */
    public static function create(int $taskId, string $titulo, int $orden = 1): array
    {
        $db = Database::getConnection();
        $now = gmdate('Y-m-d H:i:s');

        $stmt = $db->prepare("
            INSERT INTO subtareas (tarea_id, titulo, completada, orden, created_at, updated_at) 
            VALUES (:tarea_id, :titulo, 0, :orden, :created_at, :updated_at)
        ");
        $stmt->execute([
            ':tarea_id'   => $taskId,
            ':titulo'     => trim($titulo),
            ':orden'      => $orden,
            ':created_at' => $now,
            ':updated_at' => $now
        ]);

        $newId = (int)$db->lastInsertId();
        return self::findById($newId);
    }

    /**
     * Actualiza el estado de completitud, título u orden de una subtarea.
     */
    public static function update(int $id, array $data): array
    {
        $db = Database::getConnection();
        $fields = [];
        $params = [':id' => $id, ':updated_at' => gmdate('Y-m-d H:i:s')];

        if (array_key_exists('completada', $data)) {
            $fields[] = "completada = :completada";
            $params[':completada'] = !empty($data['completada']) ? 1 : 0;
        }

        if (isset($data['titulo'])) {
            $fields[] = "titulo = :titulo";
            $params[':titulo'] = trim((string)$data['titulo']);
        }

        if (isset($data['orden'])) {
            $fields[] = "orden = :orden";
            $params[':orden'] = (int)$data['orden'];
        }

        if (!empty($fields)) {
            $fields[] = "updated_at = :updated_at";
            $sql = "UPDATE subtareas SET " . implode(', ', $fields) . " WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        }

        return self::findById($id);
    }

    /**
     * Elimina una subtarea.
     */
    public static function delete(int $id): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM subtareas WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Formatea los datos de la subtarea para salida estándar.
     */
    public static function format(array $row): array
    {
        return [
            'id'         => (int)$row['id'],
            'tarea_id'   => (int)$row['tarea_id'],
            'titulo'     => $row['titulo'],
            'completada' => (bool)$row['completada'],
            'orden'      => (int)$row['orden'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
}
