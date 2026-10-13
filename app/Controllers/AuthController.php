<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Models\Audit;
use App\Models\Session;
use App\Models\User;

/**
 * Controlador de Autenticación, Sesión y Control de Acceso (RF-01, RF-02).
 */
class AuthController
{
    /**
     * Inicia sesión con credenciales email y contraseña.
     * POST /api/auth/login
     */
    public function login(array $request): void
    {
        $body = $request['body'] ?? [];
        $email = trim((string)($body['email'] ?? ''));
        $password = (string)($body['password'] ?? '');

        // Validación de campos requeridos
        if ($email === '' || $password === '') {
            Response::error("El correo electrónico y la contraseña son obligatorios.", 422, 'ERR_VALIDATION_FAILED', [
                'email'    => $email === '' ? 'Requerido' : null,
                'password' => $password === '' ? 'Requerido' : null
            ]);
            return;
        }

        $user = User::findByEmail($email);

        // Verificación segura contra timing attacks
        if ($user === null || !User::verifyPassword($password, (string)$user['password_hash'])) {
            Audit::log(
                null,
                'AUTH',
                0,
                'LOGIN_FAILED',
                null,
                null,
                ['email_intentado' => $email],
                $_SERVER['REMOTE_ADDR'] ?? null
            );

            Response::error("Credenciales de acceso incorrectas.", 401, 'ERR_INVALID_CREDENTIALS');
            return;
        }

        // Verificación de cuenta activa
        if ((int)$user['activo'] !== 1) {
            Audit::log(
                (int)$user['id'],
                'AUTH',
                (int)$user['id'],
                'LOGIN_BLOCKED_INACTIVE',
                null,
                null,
                ['email' => $email],
                $_SERVER['REMOTE_ADDR'] ?? null
            );

            Response::error("Esta cuenta de usuario se encuentra desactivada.", 403, 'ERR_USER_INACTIVE');
            return;
        }

        // Crear sesión y token de alta entropía
        $token = Session::create(
            (int)$user['id'],
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        // Establecer Cookie HttpOnly segura
        if (!headers_sent()) {
            $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
            setcookie('auth_token', $token, [
                'expires'  => time() + (12 * 3600),
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isSecure,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        }

        Audit::log(
            (int)$user['id'],
            'AUTH',
            (int)$user['id'],
            'LOGIN_SUCCESS',
            null,
            null,
            ['rol' => $user['rol']],
            $_SERVER['REMOTE_ADDR'] ?? null
        );

        Response::json([
            'user'  => User::formatPublic($user),
            'token' => $token
        ], 200);
    }

    /**
     * Cierra la sesión activa actual e invalida el token.
     * POST /api/auth/logout
     */
    public function logout(array $request): void
    {
        $user = $request['user'] ?? null;
        $token = $request['token'] ?? null;

        if ($token) {
            Session::invalidate($token);
        }

        // Eliminar Cookie HttpOnly
        if (!headers_sent()) {
            setcookie('auth_token', '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'domain'   => '',
                'secure'   => false,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        }

        if ($user) {
            Audit::log(
                (int)$user['id'],
                'AUTH',
                (int)$user['id'],
                'LOGOUT_SUCCESS',
                null,
                null,
                null,
                $_SERVER['REMOTE_ADDR'] ?? null
            );
        }

        Response::json(['message' => 'Sesión cerrada exitosamente.'], 200);
    }

    /**
     * Retorna el perfil del usuario autenticado en sesión.
     * GET /api/auth/me
     */
    public function me(array $request): void
    {
        $user = $request['user'] ?? null;

        if (!$user) {
            Response::error("No autenticado.", 401, 'ERR_UNAUTHORIZED');
            return;
        }

        Response::json([
            'user' => $user
        ], 200);
    }
}
