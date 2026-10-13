<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Response;
use App\Models\Session;
use App\Models\User;

/**
 * Middleware de Autenticación y Autorización por Rol (RBAC).
 */
class AuthMiddleware
{
    /**
     * Extrae el token de la petición (Cookie o Header Authorization).
     */
    public static function extractToken(): ?string
    {
        // 1. Verificar Cookie HttpOnly
        if (!empty($_COOKIE['auth_token'])) {
            return trim((string)$_COOKIE['auth_token']);
        }

        // 2. Verificar Header Authorization: Bearer <token>
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Valida que la petición cuente con una sesión activa.
     * Inyecta el usuario autenticado en $request['user'].
     */
    public function handle(array &$request): void
    {
        $token = self::extractToken();

        if ($token === null || $token === '') {
            Response::error("Autenticación requerida. Inicie sesión para continuar.", 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $session = Session::findByToken($token);

        if ($session === null) {
            Response::error("Sesión inválida o expirada.", 401, 'ERR_SESSION_EXPIRED');
            return;
        }

        if ((int)$session['activo'] !== 1) {
            Response::error("La cuenta de usuario se encuentra desactivada.", 403, 'ERR_USER_INACTIVE');
            return;
        }

        // Inyectar datos del usuario autenticado en la petición
        $request['user'] = [
            'id'     => (int)$session['usuario_id'],
            'codigo' => $session['codigo'],
            'nombre' => $session['nombre'],
            'email'  => $session['email'],
            'rol'    => $session['rol'],
            'cargo'  => $session['cargo'],
            'es_jefe'=> in_array($session['rol'], [User::ROL_JEFE_PERSONAL, User::ROL_JEFE_PLANEAMIENTO], true)
        ];
        $request['token'] = $token;
    }

    /**
     * Retorna un middleware invocable para restringir acceso por roles.
     */
    public static function requireRoles(array $allowedRoles): callable
    {
        return function (array &$request) use ($allowedRoles) {
            $auth = new self();
            $auth->handle($request);

            if (!isset($request['user'])) {
                return;
            }

            $userRol = $request['user']['rol'] ?? '';
            if (!in_array($userRol, $allowedRoles, true)) {
                Response::error("No posee permisos suficientes para acceder a este recurso.", 403, 'ERR_FORBIDDEN_ROLE');
                return;
            }
        };
    }
}
