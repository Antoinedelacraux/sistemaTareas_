<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Models\User;

/**
 * Controlador de Gestión de Usuarios, Ámbitos y Permisos de Asignación.
 * Cubre: RF-02, RF-05, RF-06, RF-07, RN-03, RN-04, RN-05.
 */
class UserController
{
    /**
     * Lista usuarios elegibles según el ámbito del usuario autenticado.
     * - Asistentes: Solo se ven a sí mismos (RF-05, RN-03, RN-05).
     * - Jefes: Listan a todos los miembros activos del área (RF-06, RN-04).
     *
     * GET /api/usuarios
     */
    public function index(array $request): void
    {
        $currentUser = $request['user'] ?? null;
        if (!$currentUser) {
            Response::error("Autenticación requerida.", 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $filters = $request['query'] ?? [];
        $usuarios = User::listForUser($currentUser, $filters);

        Response::json([
            'usuarios' => $usuarios,
            'total'    => count($usuarios)
        ]);
    }

    /**
     * Retorna el detalle seguro de un usuario específico.
     * Aplica control estricto de visibilidad por alcance (RF-07, RN-05, anti-IDOR/BOLA).
     *
     * GET /api/usuarios/{id}
     */
    public function show(array $request): void
    {
        $currentUser = $request['user'] ?? null;
        if (!$currentUser) {
            Response::error("Autenticación requerida.", 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $targetId = (int)($request['params']['id'] ?? 0);
        if ($targetId <= 0) {
            Response::error("Identificador de usuario inválido.", 400, 'ERR_INVALID_ID');
            return;
        }

        // Validación de permisos por alcance (Anti-IDOR)
        if (!User::canViewUser($currentUser, $targetId)) {
            Response::error(
                "No posee autorización para consultar información de otros usuarios (RF-07, RN-05).",
                403,
                'ERR_FORBIDDEN_USER_ACCESS'
            );
            return;
        }

        $user = User::findById($targetId);
        if ($user === null) {
            Response::error("Usuario no encontrado.", 404, 'ERR_USER_NOT_FOUND');
            return;
        }

        Response::json([
            'usuario' => User::formatPublic($user)
        ]);
    }

    /**
     * Valida si el usuario en sesión puede asignar tareas a un responsable destino.
     *
     * POST /api/usuarios/validar-asignacion
     * Body JSON: { "responsable_id": 2 }
     */
    public function validateAssignment(array $request): void
    {
        $currentUser = $request['user'] ?? null;
        if (!$currentUser) {
            Response::error("Autenticación requerida.", 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $body = $request['body'] ?? [];
        $responsableId = (int)($body['responsable_id'] ?? 0);

        if ($responsableId <= 0) {
            Response::error("Debe proporcionar un 'responsable_id' válido.", 400, 'ERR_INVALID_DATA');
            return;
        }

        $result = User::validateAssignment($currentUser, $responsableId);
        if (!$result['valid']) {
            Response::error($result['message'], 403, $result['code']);
            return;
        }

        Response::json([
            'valido'      => true,
            'mensaje'     => 'Asignación autorizada.',
            'responsable' => $result['target']
        ]);
    }

    /**
     * Registra un nuevo usuario en el sistema.
     * Restringido a Jefaturas (RF-02, RN-04).
     * POST /api/usuarios
     */
    public function create(array $request): void
    {
        $currentUser = $request['user'] ?? null;
        if (!$currentUser) {
            Response::error("Autenticación requerida.", 401, 'ERR_UNAUTHORIZED');
            return;
        }

        if (!User::isJefe($currentUser)) {
            Response::error("Solo el personal de Jefatura está autorizado para registrar nuevos usuarios.", 403, 'ERR_FORBIDDEN_ACTION');
            return;
        }

        $body = $request['body'] ?? [];
        $nombre = trim((string)($body['nombre'] ?? ''));
        $email = strtolower(trim((string)($body['email'] ?? '')));
        $rol = strtoupper(trim((string)($body['rol'] ?? 'ASISTENTE')));
        $cargo = trim((string)($body['cargo'] ?? 'Asistente Operativo'));
        $password = (string)($body['password'] ?? 'Planeamiento2026!');

        if ($nombre === '' || mb_strlen($nombre) > 100) {
            Response::error("El nombre completo es obligatorio y debe tener como máximo 100 caracteres.", 422, 'ERR_VALIDATION_FAILED');
            return;
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error("El correo institucional proporcionado no es válido.", 422, 'ERR_VALIDATION_FAILED');
            return;
        }

        if (User::findByEmail($email) !== null) {
            Response::error("Ya existe un usuario registrado con el correo '{$email}'.", 409, 'ERR_DUPLICATE_EMAIL');
            return;
        }

        if (!in_array($rol, [User::ROL_ASISTENTE, User::ROL_JEFE_PLANEAMIENTO, User::ROL_JEFE_PERSONAL], true)) {
            Response::error("El rol especificado no es válido.", 422, 'ERR_VALIDATION_FAILED');
            return;
        }

        if (strlen($password) < 6) {
            Response::error("La contraseña inicial debe tener al menos 6 caracteres.", 422, 'ERR_VALIDATION_FAILED');
            return;
        }

        $nuevoUsuario = User::create([
            'nombre'   => $nombre,
            'email'    => $email,
            'rol'      => $rol,
            'cargo'    => $cargo,
            'password' => $password
        ]);

        Response::json([
            'usuario' => $nuevoUsuario,
            'mensaje' => "Usuario '{$nuevoUsuario['nombre']}' registrado exitosamente."
        ], 201);
    }
}
