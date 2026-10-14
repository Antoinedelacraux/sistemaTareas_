<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

/**
 * Modelo de Columnas y Estados del Tablero Kanban (Fase 1: MVP-04).
 * Representa los estados funcionales nucleares (RF-08, RN-06).
 */
class BoardColumn
{
    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_EN_PROCESO = 'EN_PROCESO';
    public const ESTADO_BLOQUEADA = 'BLOQUEADA';
    public const ESTADO_EN_REVISION = 'EN_REVISION';
    public const ESTADO_FINALIZADA = 'FINALIZADA';

    /**
     * Lista todas las columnas activas ordenadas por su secuencia visual.
     */
    public static function listAllActive(): array
    {
        $db = Database::getConnection();
        $stmt = $db->query("
            SELECT id, nombre, orden, tipo_estado, color_hex, activo 
            FROM tableros_columnas 
            WHERE activo = 1 
            ORDER BY orden ASC
        ");
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => self::format($row), $rows);
    }

    /**
     * Busca una columna por su ID.
     */
    public static function findById(int $id): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT id, nombre, orden, tipo_estado, color_hex, activo 
            FROM tableros_columnas 
            WHERE id = :id 
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? self::format($row) : null;
    }

    /**
     * Busca la columna configurada para un tipo funcional de estado.
     */
    public static function findByTipoEstado(string $tipoEstado): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT id, nombre, orden, tipo_estado, color_hex, activo 
            FROM tableros_columnas 
            WHERE tipo_estado = :tipo_estado AND activo = 1 
            ORDER BY orden ASC 
            LIMIT 1
        ");
        $stmt->execute([':tipo_estado' => strtoupper(trim($tipoEstado))]);
        $row = $stmt->fetch();

        return $row ? self::format($row) : null;
    }

    /**
     * Formatea los datos de la columna para salida estándar.
     */
    public static function format(array $row): array
    {
        return [
            'id'          => (int)$row['id'],
            'nombre'      => $row['nombre'],
            'orden'       => (int)$row['orden'],
            'tipo_estado' => $row['tipo_estado'],
            'color_hex'   => $row['color_hex'] ?? '#3b82f6',
            'activo'      => (bool)$row['activo']
        ];
    }

    /**
     * Crea una nueva columna en el tablero.
     */
    public static function create(array $data): array
    {
        $db = Database::getConnection();
        $stmtMax = $db->query("SELECT MAX(orden) AS max_orden FROM tableros_columnas");
        $maxOrden = (int)($stmtMax->fetchColumn() ?: 0);

        $color = !empty($data['color_hex']) ? trim((string)$data['color_hex']) : '#2563eb';
        $tipo = !empty($data['tipo_estado']) ? strtoupper(trim((string)$data['tipo_estado'])) : self::ESTADO_EN_PROCESO;
        if (!in_array($tipo, [self::ESTADO_PENDIENTE, self::ESTADO_EN_PROCESO, self::ESTADO_BLOQUEADA, self::ESTADO_EN_REVISION, self::ESTADO_FINALIZADA], true)) {
            $tipo = self::ESTADO_EN_PROCESO;
        }

        $stmt = $db->prepare("
            INSERT INTO tableros_columnas (nombre, orden, tipo_estado, color_hex, activo)
            VALUES (:nombre, :orden, :tipo_estado, :color_hex, 1)
        ");
        $stmt->execute([
            ':nombre'      => trim((string)$data['nombre']),
            ':orden'       => (int)($data['orden'] ?? ($maxOrden + 1)),
            ':tipo_estado' => $tipo,
            ':color_hex'   => $color
        ]);

        $id = (int)$db->lastInsertId();
        return self::findById($id);
    }

    /**
     * Actualiza el nombre, color o estado funcional de una columna.
     */
    public static function update(int $id, array $data): ?array
    {
        $db = Database::getConnection();
        $col = self::findById($id);
        if (!$col) return null;

        $nombre = isset($data['nombre']) ? trim((string)$data['nombre']) : $col['nombre'];
        $color = isset($data['color_hex']) ? trim((string)$data['color_hex']) : $col['color_hex'];
        $tipo = isset($data['tipo_estado']) ? strtoupper(trim((string)$data['tipo_estado'])) : $col['tipo_estado'];
        $orden = isset($data['orden']) ? (int)$data['orden'] : $col['orden'];

        $stmt = $db->prepare("
            UPDATE tableros_columnas
            SET nombre = :nombre, color_hex = :color_hex, tipo_estado = :tipo_estado, orden = :orden
            WHERE id = :id
        ");
        $stmt->execute([
            ':nombre'      => $nombre,
            ':color_hex'   => $color,
            ':tipo_estado' => $tipo,
            ':orden'       => $orden,
            ':id'          => $id
        ]);

        return self::findById($id);
    }

    /**
     * Desactiva una columna siempre que no contenga tareas activas.
     */
    public static function delete(int $id): bool
    {
        $db = Database::getConnection();
        $stmtCheck = $db->prepare("SELECT COUNT(*) FROM tareas WHERE columna_id = :id");
        $stmtCheck->execute([':id' => $id]);
        $count = (int)$stmtCheck->fetchColumn();

        if ($count > 0) {
            throw new \RuntimeException("No se puede eliminar la columna porque contiene {$count} tarea(s) asociada(s). Mueva las tareas a otra columna primero.", 409);
        }

        $stmt = $db->prepare("UPDATE tableros_columnas SET activo = 0 WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
