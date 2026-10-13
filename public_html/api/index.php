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
use App\Controllers\TaskController;
use App\Controllers\UserController;
use App\Core\Middleware\AuthMiddleware;

$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->post('/api/auth/logout', [AuthController::class, 'logout'], [AuthMiddleware::class]);
$router->get('/api/auth/me', [AuthController::class, 'me'], [AuthMiddleware::class]);

// -----------------------------------------------------------------------------
// Rutas de Usuarios y Ámbitos de Asignación (MVP-02)
// -----------------------------------------------------------------------------
$router->get('/api/usuarios', [UserController::class, 'index'], [AuthMiddleware::class]);
$router->get('/api/usuarios/{id}', [UserController::class, 'show'], [AuthMiddleware::class]);
$router->post('/api/usuarios/validar-asignacion', [UserController::class, 'validateAssignment'], [AuthMiddleware::class]);

// -----------------------------------------------------------------------------
// Rutas de Tareas CRUD y Ámbito por Servidor (MVP-03)
// -----------------------------------------------------------------------------
$router->get('/api/tareas', [TaskController::class, 'index'], [AuthMiddleware::class]);
$router->get('/api/tareas/{id}', [TaskController::class, 'show'], [AuthMiddleware::class]);
$router->post('/api/tareas', [TaskController::class, 'create'], [AuthMiddleware::class]);
$router->put('/api/tareas/{id}', [TaskController::class, 'update'], [AuthMiddleware::class]);
$router->delete('/api/tareas/{id}', [TaskController::class, 'delete'], [AuthMiddleware::class]);

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
            'usuarios' => [
                'list'    => 'GET /api/usuarios',
                'detail'  => 'GET /api/usuarios/{id}',
                'validar' => 'POST /api/usuarios/validar-asignacion'
            ],
            'tareas' => [
                'list'   => 'GET /api/tareas',
                'detail' => 'GET /api/tareas/{id}',
                'create' => 'POST /api/tareas',
                'update' => 'PUT /api/tareas/{id}',
                'delete' => 'DELETE /api/tareas/{id}'
            ]
        ]
    ]);
});

// Despachar la petición HTTP
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';

$router->dispatch($method, $uri);
