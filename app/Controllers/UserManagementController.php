<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Models\Audit;
use App\Models\Session;
use App\Models\User;

/** Administración aislada de cuentas y roles para Jefatura. */
class UserManagementController
{
    private function currentJefe(array $request): ?array
    {
        $user = $request['user'] ?? null;
        if (!$user) {
            Response::error('Autenticación requerida.', 401, 'ERR_UNAUTHORIZED');
            return null;
        }
        if (!User::isJefe($user)) {
            Response::error('Acceso exclusivo para Jefatura.', 403, 'ERR_FORBIDDEN_ROLE');
            return null;
        }
        return $user;
    }

    private function target(array $request): ?array
    {
        $rawId = $request['params']['id'] ?? null;
        if (filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            Response::error('Identificador de usuario inválido.', 400, 'ERR_INVALID_ID');
            return null;
        }
        $id = (int)$rawId;
        $target = User::findById($id);
        if ($target === null) {
            Response::error('Usuario no encontrado.', 404, 'ERR_USER_NOT_FOUND');
            return null;
        }
        return $target;
    }

    private function validateData(array $data, ?int $excludeId = null): ?array
    {
        $nombre = trim((string)($data['nombre'] ?? ''));
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $rol = strtoupper(trim((string)($data['rol'] ?? '')));
        $cargo = trim((string)($data['cargo'] ?? ''));

        if ($nombre === '' || mb_strlen($nombre) > 100 || $cargo === '' || mb_strlen($cargo) > 100) {
            Response::error('Nombre y cargo son obligatorios y no pueden exceder 100 caracteres.', 422, 'ERR_VALIDATION_FAILED');
            return null;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('El correo institucional no es válido.', 422, 'ERR_VALIDATION_FAILED');
            return null;
        }
        if (!in_array($rol, User::validRoles(), true)) {
            Response::error('El rol seleccionado no es válido.', 422, 'ERR_VALIDATION_FAILED');
            return null;
        }
        $existing = User::findByEmail($email);
        if ($existing !== null && (int)$existing['id'] !== $excludeId) {
            Response::error('Ya existe una cuenta con ese correo institucional.', 409, 'ERR_DUPLICATE_EMAIL');
            return null;
        }
        return compact('nombre', 'email', 'rol', 'cargo');
    }

    public function index(array $request): void
    {
        if ($this->currentJefe($request) === null) return;
        $filters = $request['query'] ?? [];
        $usuarios = User::listAll($filters);
        Response::json(['usuarios' => $usuarios, 'total' => count($usuarios)]);
    }

    public function roles(array $request): void
    {
        if ($this->currentJefe($request) === null) return;
        Response::json(['roles' => [
            ['codigo' => User::ROL_ASISTENTE, 'nombre' => 'Asistente', 'permisos' => ['Tareas asignadas', 'Edición en Pendiente y En proceso']],
            ['codigo' => User::ROL_JEFE_PERSONAL, 'nombre' => 'Jefe de Personal', 'permisos' => ['Gestión operativa total', 'Gestión de usuarios']],
            ['codigo' => User::ROL_JEFE_PLANEAMIENTO, 'nombre' => 'Jefe de Área', 'permisos' => ['Gestión operativa total', 'Máxima jerarquía administrativa']]
        ]]);
    }

    /** Retorna una cuenta administrativa sin exponer credenciales. */
    public function show(array $request): void
    {
        if ($this->currentJefe($request) === null) return;
        $target = $this->target($request);
        if ($target === null) return;
        Response::json(['usuario' => User::formatPublic($target)]);
    }

    public function create(array $request): void
    {
        $actor = $this->currentJefe($request);
        if ($actor === null) return;
        $data = $this->validateData($request['body'] ?? []);
        if ($data === null) return;
        $password = (string)(($request['body'] ?? [])['password'] ?? '');
        if (strlen($password) < 8) {
            Response::error('La contraseña asignada debe tener al menos 8 caracteres.', 422, 'ERR_WEAK_PASSWORD');
            return;
        }
        $created = User::create($data + ['password' => $password]);
        Audit::log((int)$actor['id'], 'USUARIO', (int)$created['id'], 'USUARIO_CREADO', null, $created['rol'], ['rol' => $created['rol'], 'activo' => true]);
        Response::json(['usuario' => $created, 'mensaje' => 'Cuenta creada correctamente.'], 201);
    }

    public function update(array $request): void
    {
        $actor = $this->currentJefe($request);
        if ($actor === null) return;
        $target = $this->target($request);
        if ($target === null) return;
        $data = $this->validateData($request['body'] ?? [], (int)$target['id']);
        if ($data === null) return;
        $updated = User::updateManagement((int)$target['id'], $data);
        $roleChanged = $target['rol'] !== $updated['rol'];
        if ($roleChanged) Session::invalidateForUser((int)$target['id']);
        Audit::log((int)$actor['id'], 'USUARIO', (int)$target['id'], 'USUARIO_ACTUALIZADO', $target['rol'], $updated['rol'], ['rol_anterior' => $target['rol'], 'rol_nuevo' => $updated['rol']]);
        Response::json([
            'usuario' => User::formatPublic($updated),
            'mensaje' => 'Cuenta actualizada correctamente.',
            'requiere_reinicio' => $roleChanged
        ]);
    }

    public function changeStatus(array $request): void
    {
        $actor = $this->currentJefe($request);
        if ($actor === null) return;
        $target = $this->target($request);
        if ($target === null) return;
        $body = $request['body'] ?? [];
        if (!array_key_exists('activo', $body)) {
            Response::error('Debe indicar el estado activo de la cuenta.', 422, 'ERR_VALIDATION_FAILED');
            return;
        }
        $active = filter_var($body['activo'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($active === null) {
            Response::error('El estado de la cuenta no es válido.', 422, 'ERR_VALIDATION_FAILED');
            return;
        }
        if (!$active && ($actor['rol'] ?? '') === User::ROL_JEFE_PERSONAL && ($target['rol'] ?? '') === User::ROL_JEFE_PLANEAMIENTO) {
            Response::error('Jefe de Personal no puede desactivar una cuenta con rol Jefe de Área.', 403, 'ERR_HIERARCHY_PROTECTED');
            return;
        }
        $updated = User::setActive((int)$target['id'], $active);
        if (!$active) Session::invalidateForUser((int)$target['id']);
        Audit::log((int)$actor['id'], 'USUARIO', (int)$target['id'], $active ? 'USUARIO_ACTIVADO' : 'USUARIO_DESACTIVADO', $target['activo'] ? 'ACTIVO' : 'INACTIVO', $active ? 'ACTIVO' : 'INACTIVO');
        Response::json(['usuario' => User::formatPublic($updated), 'mensaje' => $active ? 'Cuenta activada.' : 'Cuenta desactivada.']);
    }

    public function changePassword(array $request): void
    {
        $actor = $this->currentJefe($request);
        if ($actor === null) return;
        $target = $this->target($request);
        if ($target === null) return;
        $password = (string)(($request['body'] ?? [])['password'] ?? '');
        if (strlen($password) < 8) {
            Response::error('La nueva contraseña debe tener al menos 8 caracteres.', 422, 'ERR_WEAK_PASSWORD');
            return;
        }
        User::changePassword((int)$target['id'], $password);
        Session::invalidateForUser((int)$target['id']);
        Audit::log((int)$actor['id'], 'USUARIO', (int)$target['id'], 'PASSWORD_CAMBIADA');
        Response::json(['mensaje' => 'Contraseña actualizada. La cuenta deberá iniciar sesión nuevamente.']);
    }
}
