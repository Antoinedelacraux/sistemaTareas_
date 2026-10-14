<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;
use RuntimeException;

/** Columnas Kanban aisladas por ámbito: Área compartida o Personal por usuario. */
class BoardColumn
{
    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_EN_PROCESO = 'EN_PROCESO';
    public const ESTADO_BLOQUEADA = 'BLOQUEADA';
    public const ESTADO_EN_REVISION = 'EN_REVISION';
    public const ESTADO_FINALIZADA = 'FINALIZADA';
    public const AMBITO_AREA = 'AREA';
    public const AMBITO_PERSONAL = 'PERSONAL';

    private static bool $schemaReady = false;

    public static function ensureSchema(): void
    {
        if (self::$schemaReady) return;
        $db = Database::getConnection();
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $columns = $driver === 'sqlite'
            ? array_column($db->query('PRAGMA table_info(tableros_columnas)')->fetchAll(), 'name')
            : array_column($db->query('SHOW COLUMNS FROM tableros_columnas')->fetchAll(), 'Field');

        if (!in_array('ambito', $columns, true)) {
            $db->exec("ALTER TABLE tableros_columnas ADD COLUMN ambito " . ($driver === 'sqlite' ? "TEXT NOT NULL DEFAULT 'AREA'" : "ENUM('AREA','PERSONAL') NOT NULL DEFAULT 'AREA'"));
        }
        if (!in_array('usuario_id', $columns, true)) {
            $db->exec('ALTER TABLE tableros_columnas ADD COLUMN usuario_id ' . ($driver === 'sqlite' ? 'INTEGER NULL' : 'INT UNSIGNED NULL'));
        }
        $db->exec("UPDATE tableros_columnas SET ambito = 'AREA' WHERE ambito IS NULL OR ambito = ''");
        self::$schemaReady = true;
        self::migrateExistingPersonalTasks();
    }

    private static function migrateExistingPersonalTasks(): void
    {
        $db = Database::getConnection();
        $owners = $db->query("SELECT DISTINCT responsable_id FROM tareas WHERE tipo_tarea = 'PERSONAL'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($owners as $ownerId) {
            self::ensurePersonalColumns((int)$ownerId);
            $stmt = $db->prepare("SELECT id, columna_id FROM tareas WHERE tipo_tarea = 'PERSONAL' AND responsable_id = :owner");
            $stmt->execute([':owner' => (int)$ownerId]);
            foreach ($stmt->fetchAll() as $task) {
                $column = self::findById((int)$task['columna_id']);
                if (($column['ambito'] ?? self::AMBITO_AREA) === self::AMBITO_PERSONAL && (int)$column['usuario_id'] === (int)$ownerId) continue;
                $source = self::findById((int)$task['columna_id']);
                $replacement = self::findByTipoEstado($source['tipo_estado'] ?? self::ESTADO_PENDIENTE, self::AMBITO_PERSONAL, (int)$ownerId);
                if ($replacement) {
                    $move = $db->prepare('UPDATE tareas SET columna_id = :column_id WHERE id = :id');
                    $move->execute([':column_id' => $replacement['id'], ':id' => (int)$task['id']]);
                }
            }
        }
    }

    public static function normalizeScope(?string $scope): string
    {
        return strtoupper(trim((string)$scope)) === self::AMBITO_PERSONAL ? self::AMBITO_PERSONAL : self::AMBITO_AREA;
    }

    public static function ensurePersonalColumns(int $userId): void
    {
        if ($userId <= 0) throw new RuntimeException('Se requiere un usuario para el tablero personal.', 422);
        $db = Database::getConnection();
        $exists = $db->prepare("SELECT COUNT(*) FROM tableros_columnas WHERE ambito = 'PERSONAL' AND usuario_id = :user_id");
        $exists->execute([':user_id' => $userId]);
        if ((int)$exists->fetchColumn() > 0) return;

        $area = $db->query("SELECT nombre, orden, tipo_estado, color_hex, activo FROM tableros_columnas WHERE ambito = 'AREA' ORDER BY orden, id")->fetchAll();
        foreach ($area as $column) {
            $insert = $db->prepare("INSERT INTO tableros_columnas (nombre, orden, tipo_estado, color_hex, activo, ambito, usuario_id) VALUES (:nombre, :orden, :tipo_estado, :color_hex, :activo, 'PERSONAL', :usuario_id)");
            $insert->execute([
                ':nombre' => $column['nombre'], ':orden' => $column['orden'], ':tipo_estado' => $column['tipo_estado'],
                ':color_hex' => $column['color_hex'], ':activo' => $column['activo'], ':usuario_id' => $userId
            ]);
        }
    }

    public static function listAllActive(string $scope = self::AMBITO_AREA, ?int $userId = null): array
    {
        self::ensureSchema();
        $scope = self::normalizeScope($scope);
        if ($scope === self::AMBITO_PERSONAL) self::ensurePersonalColumns((int)$userId);
        $db = Database::getConnection();
        $sql = 'SELECT id, nombre, orden, tipo_estado, color_hex, activo, ambito, usuario_id FROM tableros_columnas WHERE activo = 1 AND ambito = :ambito';
        $params = [':ambito' => $scope];
        if ($scope === self::AMBITO_PERSONAL) { $sql .= ' AND usuario_id = :usuario_id'; $params[':usuario_id'] = (int)$userId; }
        $sql .= ' ORDER BY orden ASC, id ASC';
        $stmt = $db->prepare($sql); $stmt->execute($params);
        return array_map(fn($row) => self::format($row), $stmt->fetchAll());
    }

    public static function findById(int $id): ?array
    {
        self::ensureSchema();
        $stmt = Database::getConnection()->prepare('SELECT id, nombre, orden, tipo_estado, color_hex, activo, ambito, usuario_id FROM tableros_columnas WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]); $row = $stmt->fetch();
        return $row ? self::format($row) : null;
    }

    public static function findByTipoEstado(string $tipoEstado, string $scope = self::AMBITO_AREA, ?int $userId = null): ?array
    {
        $columns = self::listAllActive($scope, $userId);
        foreach ($columns as $column) if ($column['tipo_estado'] === strtoupper(trim($tipoEstado))) return $column;
        return null;
    }

    public static function format(array $row): array
    {
        return ['id'=>(int)$row['id'], 'nombre'=>$row['nombre'], 'orden'=>(int)$row['orden'], 'tipo_estado'=>$row['tipo_estado'], 'color_hex'=>$row['color_hex'] ?? '#3b82f6', 'activo'=>(bool)$row['activo'], 'ambito'=>self::normalizeScope($row['ambito'] ?? self::AMBITO_AREA), 'usuario_id'=>isset($row['usuario_id']) ? (int)$row['usuario_id'] : null];
    }

    public static function create(array $data): array
    {
        self::ensureSchema(); $db = Database::getConnection();
        $scope = self::normalizeScope($data['ambito'] ?? null); $owner = $scope === self::AMBITO_PERSONAL ? (int)($data['usuario_id'] ?? 0) : null;
        if ($scope === self::AMBITO_PERSONAL && $owner <= 0) throw new RuntimeException('La columna personal requiere propietario.', 422);
        if ($scope === self::AMBITO_PERSONAL) self::ensurePersonalColumns($owner);
        $max = $db->prepare('SELECT MAX(orden) FROM tableros_columnas WHERE ambito = :ambito AND ((:usuario_id IS NULL AND usuario_id IS NULL) OR usuario_id = :usuario_id)');
        $max->execute([':ambito'=>$scope, ':usuario_id'=>$owner]);
        $raw = strtoupper(trim((string)($data['tipo_estado'] ?? self::ESTADO_EN_PROCESO)));
        $type = match ($raw) {'REVISION','REVISION_APROBACION',self::ESTADO_EN_REVISION=>self::ESTADO_EN_REVISION,'FINALIZADA','CUMPLIDA',self::ESTADO_FINALIZADA=>self::ESTADO_FINALIZADA,'EN_CURSO',self::ESTADO_EN_PROCESO=>self::ESTADO_EN_PROCESO,'POR_HACER',self::ESTADO_PENDIENTE=>self::ESTADO_PENDIENTE,self::ESTADO_BLOQUEADA=>self::ESTADO_BLOQUEADA,default=>self::ESTADO_EN_PROCESO};
        $stmt = $db->prepare('INSERT INTO tableros_columnas (nombre, orden, tipo_estado, color_hex, activo, ambito, usuario_id) VALUES (:nombre,:orden,:tipo_estado,:color_hex,1,:ambito,:usuario_id)');
        $stmt->execute([':nombre'=>trim((string)$data['nombre']), ':orden'=>(int)($data['orden'] ?? ((int)$max->fetchColumn()+1)), ':tipo_estado'=>$type, ':color_hex'=>trim((string)($data['color_hex'] ?? '#2563eb')), ':ambito'=>$scope, ':usuario_id'=>$owner]);
        return self::findById((int)$db->lastInsertId());
    }

    public static function update(int $id, array $data): ?array
    {
        $col = self::findById($id); if (!$col) return null;
        $db=Database::getConnection(); $nombre=isset($data['nombre'])?trim((string)$data['nombre']):$col['nombre']; $color=isset($data['color_hex'])?trim((string)$data['color_hex']):$col['color_hex']; $orden=isset($data['orden'])?(int)$data['orden']:$col['orden'];
        $raw=strtoupper(trim((string)($data['tipo_estado']??$col['tipo_estado']))); $tipo=match($raw){'REVISION','REVISION_APROBACION',self::ESTADO_EN_REVISION=>self::ESTADO_EN_REVISION,'FINALIZADA','CUMPLIDA',self::ESTADO_FINALIZADA=>self::ESTADO_FINALIZADA,'EN_CURSO',self::ESTADO_EN_PROCESO=>self::ESTADO_EN_PROCESO,'POR_HACER',self::ESTADO_PENDIENTE=>self::ESTADO_PENDIENTE,self::ESTADO_BLOQUEADA=>self::ESTADO_BLOQUEADA,default=>$col['tipo_estado']};
        $stmt=$db->prepare('UPDATE tableros_columnas SET nombre=:nombre,color_hex=:color_hex,tipo_estado=:tipo_estado,orden=:orden WHERE id=:id'); $stmt->execute([':nombre'=>$nombre,':color_hex'=>$color,':tipo_estado'=>$tipo,':orden'=>$orden,':id'=>$id]); return self::findById($id);
    }

    public static function delete(int $id): bool
    {
        $col=self::findById($id); if (!$col) return false; $db=Database::getConnection();
        $check=$db->prepare('SELECT COUNT(*) FROM tareas WHERE columna_id=:id'); $check->execute([':id'=>$id]); $count=(int)$check->fetchColumn();
        if($count>0) throw new RuntimeException("No se puede eliminar la columna porque contiene {$count} tarea(s) asociada(s). Mueva las tareas a otra columna primero.",409);
        return $db->prepare('UPDATE tableros_columnas SET activo=0 WHERE id=:id')->execute([':id'=>$id]);
    }
}
