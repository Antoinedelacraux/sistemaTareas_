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

        $user = User::findById($targetId);
        if ($user === null && User::isJefe($currentUser)) {
            Response::error("Usuario no encontrado.", 404, 'ERR_USER_NOT_FOUND');
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

}
