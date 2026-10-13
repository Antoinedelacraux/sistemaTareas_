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
use App\Controllers\BoardController;

$router->get('/api/tareas', [TaskController::class, 'index'], [AuthMiddleware::class]);
$router->get('/api/tareas/{id}', [TaskController::class, 'show'], [AuthMiddleware::class]);
$router->post('/api/tareas', [TaskController::class, 'create'], [AuthMiddleware::class]);
$router->put('/api/tareas/{id}', [TaskController::class, 'update'], [AuthMiddleware::class]);
$router->delete('/api/tareas/{id}', [TaskController::class, 'delete'], [AuthMiddleware::class]);

// -----------------------------------------------------------------------------
// Rutas de Tablero Kanban y Transiciones de Estado (MVP-04)
// -----------------------------------------------------------------------------
$router->get('/api/tableros/columnas', [BoardController::class, 'columns'], [AuthMiddleware::class]);
$router->get('/api/tableros/kanban', [BoardController::class, 'kanban'], [AuthMiddleware::class]);
$router->post('/api/tareas/{id}/transicion', [BoardController::class, 'transition'], [AuthMiddleware::class]);

// -----------------------------------------------------------------------------
// Rutas de Subtareas, Comentarios y Evidencias Seguras (MVP-05)
// -----------------------------------------------------------------------------
use App\Controllers\SubtaskController;
use App\Controllers\CommentController;

$router->get('/api/tareas/{id}/subtareas', [SubtaskController::class, 'index'], [AuthMiddleware::class]);
$router->post('/api/tareas/{id}/subtareas', [SubtaskController::class, 'create'], [AuthMiddleware::class]);
$router->patch('/api/subtareas/{id}', [SubtaskController::class, 'update'], [AuthMiddleware::class]);
$router->delete('/api/subtareas/{id}', [SubtaskController::class, 'delete'], [AuthMiddleware::class]);

$router->get('/api/tareas/{id}/comentarios', [CommentController::class, 'index'], [AuthMiddleware::class]);
$router->post('/api/tareas/{id}/comentarios', [CommentController::class, 'create'], [AuthMiddleware::class]);
$router->get('/api/comentarios/{id}/archivo', [CommentController::class, 'download'], [AuthMiddleware::class]);

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
                'list'       => 'GET /api/tareas',
                'detail'     => 'GET /api/tareas/{id}',
                'create'     => 'POST /api/tareas',
                'update'     => 'PUT /api/tareas/{id}',
                'delete'     => 'DELETE /api/tareas/{id}',
                'transicion' => 'POST /api/tareas/{id}/transicion'
            ],
            'tableros' => [
                'columnas' => 'GET /api/tableros/columnas',
                'kanban'   => 'GET /api/tableros/kanban'
            ],
            'subtareas' => [
                'list_or_create' => 'GET|POST /api/tareas/{id}/subtareas',
                'update_delete'  => 'PATCH|DELETE /api/subtareas/{id}'
            ],
            'comentarios' => [
                'list_or_create' => 'GET|POST /api/tareas/{id}/comentarios',
                'archivo'        => 'GET /api/comentarios/{id}/archivo'
            ]
        ]
    ]);
});

// Despachar la petición HTTP
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';

$router->dispatch($method, $uri);
