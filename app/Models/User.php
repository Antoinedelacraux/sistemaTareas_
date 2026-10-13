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
}
