<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

/**
 * Modelo de Usuario y control de roles RBAC (RF-01, RF-02).
 */
class User
{
    public const ROL_ASISTENTE = 'ASISTENTE';
    public const ROL_JEFE_PERSONAL = 'JEFE_PERSONAL';
    public const ROL_JEFE_PLANEAMIENTO = 'JEFE_PLANEAMIENTO';

    /**
     * Busca usuario por email.
     */
    public static function findByEmail(string $email): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => trim($email)]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Busca usuario por ID.
     */
    public static function findById(int $id): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Busca el primer usuario activo con un rol específico.
     */
    public static function findByRol(string $rol): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE rol = :rol AND activo = 1 ORDER BY id ASC LIMIT 1");
        $stmt->execute([':rol' => $rol]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Comprueba si un usuario tiene rol de jefatura.
     */
    public static function isJefe(array $user): bool
    {
        return in_array($user['rol'] ?? '', [self::ROL_JEFE_PERSONAL, self::ROL_JEFE_PLANEAMIENTO], true);
    }

    /**
     * Verifica la contraseña usando password_verify de PHP.
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Retorna datos públicos seguros del usuario (sin password_hash).
     */
    public static function formatPublic(array $user): array
    {
        return [
            'id'     => (int)$user['id'],
            'codigo' => $user['codigo'],
            'nombre' => $user['nombre'],
            'email'  => $user['email'],
            'rol'    => $user['rol'],
            'cargo'  => $user['cargo'],
            'activo' => (bool)$user['activo'],
            'es_jefe'=> in_array($user['rol'], [self::ROL_JEFE_PERSONAL, self::ROL_JEFE_PLANEAMIENTO], true)
        ];
    }

    /**
     * Lista todos los usuarios con filtros opcionales (Solo administradores/jefes).
     *
     * @param array $filters ['activo' => bool|int, 'rol' => string, 'buscar' => string]
     * @return array
     */
    public static function listAll(array $filters = []): array
    {
        $db = Database::getConnection();
        $sql = "SELECT id, codigo, nombre, email, rol, cargo, activo, created_at, updated_at FROM usuarios WHERE 1=1";
        $params = [];

        // Filtro por estado activo (por defecto solo usuarios activos)
        if (isset($filters['activo'])) {
            $sql .= " AND activo = :activo";
            $params[':activo'] = (int)$filters['activo'];
        } else {
            $sql .= " AND activo = 1";
        }

        // Filtro por rol
        if (!empty($filters['rol'])) {
            $sql .= " AND rol = :rol";
            $params[':rol'] = trim((string)$filters['rol']);
        }

        // Filtro de búsqueda textual
        if (!empty($filters['buscar'])) {
            $sql .= " AND (nombre LIKE :buscar OR codigo LIKE :buscar OR email LIKE :buscar)";
            $params[':buscar'] = '%' . trim((string)$filters['buscar']) . '%';
        }

        $sql .= " ORDER BY rol ASC, nombre ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => self::formatPublic($row), $rows);
    }

    /**
     * Lista usuarios elegibles según el ámbito y rol del usuario autenticado (RF-05, RF-06, RN-03, RN-04, RN-05).
     * - Asistentes: Solo se ven a sí mismos (no pueden listar ni asignar a otros asistentes).
     * - Jefes: Pueden listar a todo el personal del área para asignaciones y supervisión.
     */
    public static function listForUser(array $currentUser, array $filters = []): array
    {
        $isJefe = in_array($currentUser['rol'] ?? '', [self::ROL_JEFE_PERSONAL, self::ROL_JEFE_PLANEAMIENTO], true);

        if ($isJefe || ($filters['scope'] ?? '') === 'team' || ($filters['contexto'] ?? '') === 'equipo' || ($filters['context'] ?? '') === 'team') {
            return self::listAll($filters);
        }

        // Si es asistente y no solicita scope=team, su alcance de asignación es estrictamente a sí mismo
        $me = self::findById((int)$currentUser['id']);
        if ($me === null || (int)$me['activo'] !== 1) {
            return [];
        }

        return [self::formatPublic($me)];
    }

    /**
     * Determina si el usuario solicitante tiene permiso para consultar el perfil de otro usuario (RF-07, RN-05).
     * - Jefes pueden consultar el perfil de cualquier miembro de su área.
     * - Asistentes solo pueden consultar su propio perfil.
     */
    public static function canViewUser(array $currentUser, int $targetUserId): bool
    {
        $isJefe = in_array($currentUser['rol'] ?? '', [self::ROL_JEFE_PERSONAL, self::ROL_JEFE_PLANEAMIENTO], true);
        if ($isJefe) {
            return true;
        }

        return (int)$currentUser['id'] === $targetUserId;
    }

    /**
     * Determina si el usuario autenticado tiene autorización para asignar una tarea al usuario objetivo (RF-05, RF-06, RN-03, RN-04).
     * - Jefes pueden asignar a cualquier usuario activo.
     * - Asistentes únicamente pueden asignarse tareas a sí mismos.
     */
    public static function canAssignTo(array $currentUser, int $targetUserId): bool
    {
        $isJefe = in_array($currentUser['rol'] ?? '', [self::ROL_JEFE_PERSONAL, self::ROL_JEFE_PLANEAMIENTO], true);
        if ($isJefe) {
            return true;
        }

        return (int)$currentUser['id'] === $targetUserId;
    }

    /**
     * Valida integralmente la asignación de una tarea a un responsable, verificando
     * permisos por rol y existencia/estado activo del usuario de destino.
     */
    public static function validateAssignment(array $currentUser, int $targetUserId): array
    {
        if (!self::canAssignTo($currentUser, $targetUserId)) {
            return [
                'valid'   => false,
                'code'    => 'ERR_FORBIDDEN_ASSIGNMENT',
                'message' => 'Los asistentes no tienen autorización para asignar tareas a otros usuarios (RF-05, RN-03).'
            ];
        }

        $target = self::findById($targetUserId);
        if ($target === null) {
            return [
                'valid'   => false,
                'code'    => 'ERR_ASSIGNEE_NOT_FOUND',
                'message' => 'El responsable seleccionado no existe en el sistema.'
            ];
        }

        if ((int)$target['activo'] !== 1) {
            return [
                'valid'   => false,
                'code'    => 'ERR_ASSIGNEE_INACTIVE',
                'message' => 'El responsable seleccionado se encuentra inactivo y no puede recibir tareas.'
            ];
        }

        return [
            'valid'  => true,
            'target' => self::formatPublic($target)
        ];
    }
}
