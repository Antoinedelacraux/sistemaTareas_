<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Models\User;
use PDO;
use RuntimeException;

/** Aprovisiona la primera cuenta administrativa sobre una base vacia. */
class InitialSetupService
{
    public static function hasUsers(): bool
    {
        return (int) Database::getConnection()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0;
    }

    /**
     * Crea el unico administrador inicial y las columnas operativas por defecto.
     * No acepta reutilizar una base que ya tenga cuentas.
     */
    public static function createFirstAdmin(array $data): array
    {
        $name = trim((string) ($data['nombre'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        $cargo = trim((string) ($data['cargo'] ?? 'Jefe de Área'));

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Ingresa nombre y correo institucional validos.', 422);
        }
        if (strlen($password) < 12) {
            throw new RuntimeException('La contraseña inicial debe tener al menos 12 caracteres.', 422);
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            if ((int) $db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0) {
                throw new RuntimeException('La instalación ya tiene usuarios y no puede inicializarse otra vez.', 409);
            }

            self::ensureDefaultColumns($db);
            $admin = User::create([
                'nombre' => $name,
                'email' => $email,
                'password' => $password,
                'rol' => User::ROL_JEFE_PLANEAMIENTO,
                'cargo' => $cargo === '' ? 'Jefe de Área' : $cargo
            ]);
            $db->commit();
            return $admin;
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    private static function ensureDefaultColumns(PDO $db): void
    {
        if ((int) $db->query('SELECT COUNT(*) FROM tableros_columnas')->fetchColumn() > 0) {
            return;
        }

        $columns = [
            ['Pendiente', 1, 'PENDIENTE', '#64748b'],
            ['En proceso', 2, 'EN_PROCESO', '#2563eb'],
            ['Bloqueada', 3, 'BLOQUEADA', '#e11d48'],
            ['En revisión', 4, 'EN_REVISION', '#d97706'],
            ['Finalizada', 5, 'FINALIZADA', '#059669']
        ];
        $stmt = $db->prepare('INSERT INTO tableros_columnas (nombre, orden, tipo_estado, color_hex, ambito, activo) VALUES (:nombre, :orden, :tipo_estado, :color_hex, :ambito, 1)');
        foreach ($columns as [$name, $order, $state, $color]) {
            $stmt->execute([
                ':nombre' => $name,
                ':orden' => $order,
                ':tipo_estado' => $state,
                ':color_hex' => $color,
                ':ambito' => 'AREA'
            ]);
        }
    }
}
