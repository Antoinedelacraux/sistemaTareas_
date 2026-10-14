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
        'version'     => '1.4.0',
        'php_version' => PHP_VERSION,
        'environment' => \App\Config\Env::get('APP_ENV', 'development'),
        'timestamp'   => gmdate('Y-m-d\TH:i:s\Z')
    ]);
});

// Endpoint de Diagnóstico y Pruebas Web Autónomas (sin necesidad de PowerShell)
$router->get('/api/test-runner', [\App\Controllers\TestRunnerController::class, 'run']);

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
$router->post('/api/usuarios', [UserController::class, 'create'], [AuthMiddleware::class]);
$router->post('/api/usuarios/validar-asignacion', [UserController::class, 'validateAssignment'], [AuthMiddleware::class]);

// -----------------------------------------------------------------------------
// Rutas de Tareas CRUD y Ámbito por Servidor (MVP-03)
// -----------------------------------------------------------------------------
use App\Controllers\BoardController;

$router->get('/api/tareas', [TaskController::class, 'index'], [AuthMiddleware::class]);
$router->get('/api/tareas/{id}', [TaskController::class, 'show'], [AuthMiddleware::class]);
$router->post('/api/tareas', [TaskController::class, 'create'], [AuthMiddleware::class]);
$router->delete('/api/tareas/personales', [TaskController::class, 'deleteAllPersonal'], [AuthMiddleware::class]);
$router->put('/api/tareas/{id}', [TaskController::class, 'update'], [AuthMiddleware::class]);
$router->patch('/api/tareas/{id}', [TaskController::class, 'update'], [AuthMiddleware::class]);
$router->delete('/api/tareas/{id}', [TaskController::class, 'delete'], [AuthMiddleware::class]);

// -----------------------------------------------------------------------------
// Rutas de Tablero Kanban y Transiciones de Estado (MVP-04)
// -----------------------------------------------------------------------------
$router->get('/api/tableros/columnas', [BoardController::class, 'columns'], [AuthMiddleware::class]);
$router->post('/api/tableros/columnas', [BoardController::class, 'createColumn'], [AuthMiddleware::class]);
$router->put('/api/tableros/columnas/{id}', [BoardController::class, 'updateColumn'], [AuthMiddleware::class]);
$router->delete('/api/tableros/columnas/{id}', [BoardController::class, 'deleteColumn'], [AuthMiddleware::class]);
$router->get('/api/tableros/kanban', [BoardController::class, 'kanban'], [AuthMiddleware::class]);
$router->post('/api/tareas/{id}/transicion', [BoardController::class, 'transition'], [AuthMiddleware::class]);
$router->patch('/api/tareas/{id}/transicion', [BoardController::class, 'transition'], [AuthMiddleware::class]);
$router->post('/api/tareas/{id}/transicionar', [BoardController::class, 'transition'], [AuthMiddleware::class]);
$router->patch('/api/tareas/{id}/transicionar', [BoardController::class, 'transition'], [AuthMiddleware::class]);

// -----------------------------------------------------------------------------
// Rutas de Subtareas, Comentarios y Evidencias Seguras (MVP-05)
// -----------------------------------------------------------------------------
use App\Controllers\SubtaskController;
use App\Controllers\CommentController;

$router->get('/api/tareas/{id}/subtareas', [SubtaskController::class, 'index'], [AuthMiddleware::class]);
$router->post('/api/tareas/{id}/subtareas', [SubtaskController::class, 'create'], [AuthMiddleware::class]);
$router->patch('/api/subtareas/{id}', [SubtaskController::class, 'update'], [AuthMiddleware::class]);
$router->put('/api/subtareas/{id}', [SubtaskController::class, 'update'], [AuthMiddleware::class]);
$router->delete('/api/subtareas/{id}', [SubtaskController::class, 'delete'], [AuthMiddleware::class]);

$router->get('/api/tareas/{id}/comentarios', [CommentController::class, 'index'], [AuthMiddleware::class]);
$router->post('/api/tareas/{id}/comentarios', [CommentController::class, 'create'], [AuthMiddleware::class]);
$router->get('/api/comentarios/{id}/archivo', [CommentController::class, 'download'], [AuthMiddleware::class]);

// -----------------------------------------------------------------------------
// Rutas de Aprobaciones y Circuito Formal de Revisión (MVP-06)
// -----------------------------------------------------------------------------
use App\Controllers\ApprovalController;

$router->post('/api/tareas/{id}/solicitar-aprobacion', [ApprovalController::class, 'solicitar'], [AuthMiddleware::class]);
$router->post('/api/tareas/{id}/resolver-aprobacion', [ApprovalController::class, 'resolver'], [AuthMiddleware::class]);
$router->get('/api/aprobaciones/pendientes', [ApprovalController::class, 'pendientes'], [AuthMiddleware::class]);
$router->get('/api/tareas/{id}/aprobaciones', [ApprovalController::class, 'historial'], [AuthMiddleware::class]);

// -----------------------------------------------------------------------------
// Rutas de Dependencias entre Tareas - DAG (MVP-07)
// -----------------------------------------------------------------------------
use App\Controllers\DependencyController;

$router->get('/api/tareas/{id}/dependencias', [DependencyController::class, 'index'], [AuthMiddleware::class]);
$router->post('/api/tareas/{id}/dependencias', [DependencyController::class, 'store'], [AuthMiddleware::class]);
$router->delete('/api/tareas/{id}/dependencias/{pred_id}', [DependencyController::class, 'destroy'], [AuthMiddleware::class]);

// -----------------------------------------------------------------------------
// Rutas de Notificaciones y Automatizaciones (MVP-08)
// -----------------------------------------------------------------------------
use App\Controllers\NotificationController;

$router->get('/api/notificaciones', [NotificationController::class, 'index'], [AuthMiddleware::class]);
$router->get('/api/notificaciones/resumen', [NotificationController::class, 'unreadCount'], [AuthMiddleware::class]);
$router->patch('/api/notificaciones/{id}/leer', [NotificationController::class, 'markAsRead'], [AuthMiddleware::class]);
$router->post('/api/notificaciones/{id}/leer', [NotificationController::class, 'markAsRead'], [AuthMiddleware::class]);
$router->post('/api/notificaciones/marcar-todas-leidas', [NotificationController::class, 'markAllAsRead'], [AuthMiddleware::class]);

// -----------------------------------------------------------------------------
// Rutas de Historial y Auditoría (Historial archivado > 24h)
// -----------------------------------------------------------------------------
use App\Controllers\AuditController;
use App\Controllers\HistoryController;

$router->get('/api/tareas/{id}/historial', [AuditController::class, 'taskHistory'], [AuthMiddleware::class]);
$router->get('/api/auditoria', [AuditController::class, 'index'], [AuthMiddleware::class]);
$router->get('/api/historial', [HistoryController::class, 'index'], [AuthMiddleware::class]);

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
            ],
            'aprobaciones' => [
                'solicitar'  => 'POST /api/tareas/{id}/solicitar-aprobacion',
                'resolver'   => 'POST /api/tareas/{id}/resolver-aprobacion',
                'pendientes' => 'GET /api/aprobaciones/pendientes',
                'historial'  => 'GET /api/tareas/{id}/aprobaciones'
            ],
            'dependencias' => [
                'list_or_create' => 'GET|POST /api/tareas/{id}/dependencias',
                'delete'         => 'DELETE /api/tareas/{id}/dependencias/{pred_id}'
            ],
            'notificaciones' => [
                'list'           => 'GET /api/notificaciones',
                'resumen'        => 'GET /api/notificaciones/resumen',
                'marcar_leida'   => 'PATCH /api/notificaciones/{id}/leer',
                'marcar_todas'   => 'POST /api/notificaciones/marcar-todas-leidas'
            ],
            'auditoria' => [
                'historial_tarea'=> 'GET /api/tareas/{id}/historial',
                'eventos'        => 'GET /api/auditoria'
            ],
            'historial' => [
                'archivadas'     => 'GET /api/historial'
            ]
        ]
    ]);
});

// Despachar la petición HTTP
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';

$router->dispatch($method, $uri);
