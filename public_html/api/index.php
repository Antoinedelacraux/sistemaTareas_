<?php

declare(strict_types=1);

// Cargar inicialización del backend
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Router;
use App\Core\Response;

$router = new Router();

// -----------------------------------------------------------------------------
// Rutas de Verificación y Diagnóstico del Sistema (Healthcheck)
// -----------------------------------------------------------------------------
$router->get('/api/health', function () {
    Response::json([
        'status'      => 'ok',
        'sistema'     => 'Sistema de Gestión y Seguimiento de Tareas - Planeamiento TAL',
        'version'     => '1.2.0',
        'php_version' => PHP_VERSION,
        'environment' => \App\Config\Env::get('APP_ENV', 'development'),
        'timestamp'   => gmdate('Y-m-d\TH:i:s\Z')
    ]);
});

// -----------------------------------------------------------------------------
// Rutas de Autenticación y Sesión (MVP-01)
// -----------------------------------------------------------------------------
use App\Controllers\AuthController;
use App\Core\Middleware\AuthMiddleware;

$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->post('/api/auth/logout', [AuthController::class, 'logout'], [AuthMiddleware::class]);
$router->get('/api/auth/me', [AuthController::class, 'me'], [AuthMiddleware::class]);

// Rutas base informativas
$router->get('/api', function () {
    Response::json([
        'message' => 'API REST - Sistema de Tareas (Planeamiento)',
        'endpoints' => [
            'health' => '/api/health',
            'auth'   => [
                'login'  => 'POST /api/auth/login',
                'logout' => 'POST /api/auth/logout',
                'me'     => 'GET /api/auth/me'
            ],
            'tareas' => '/api/tareas'
        ]
    ]);
});

// Despachar la petición HTTP
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';

$router->dispatch($method, $uri);
