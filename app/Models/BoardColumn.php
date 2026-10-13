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
            'color_hex'   => $row['color_hex'],
            'activo'      => (bool)$row['activo']
        ];
    }
}
