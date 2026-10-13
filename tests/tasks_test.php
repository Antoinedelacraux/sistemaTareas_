<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Config\Database;
use App\Controllers\TaskController;
use App\Core\Middleware\AuthMiddleware;
use App\Core\Response;
use App\Core\Router;
use App\Models\Audit;
use App\Models\Session;
use App\Models\Task;
use App\Models\User;

// Desactivar exit automático para evaluar toda la suite en memoria
Response::$exitOnSend = false;

echo "=== INICIANDO SUITE DE PRUEBAS AUTOMATIZADAS: MVP-03 (CRUD TAREAS Y REGLAS DE ALCANCE) ===\n\n";

// -----------------------------------------------------------------------------
// 1. Configurar Entorno de Pruebas Aislado con SQLite en memoria
// -----------------------------------------------------------------------------
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$pdo->exec("
    CREATE TABLE usuarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        codigo TEXT NOT NULL UNIQUE,
        nombre TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        rol TEXT NOT NULL,
        cargo TEXT NOT NULL,
        activo INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    );

    CREATE TABLE sesiones (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        usuario_id INTEGER NOT NULL,
        token_hash TEXT NOT NULL UNIQUE,
        ip_address TEXT,
        user_agent TEXT,
        expira_at TEXT NOT NULL,
        created_at TEXT NOT NULL,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    );

    CREATE TABLE tableros_columnas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        orden INTEGER NOT NULL,
        tipo_estado TEXT NOT NULL,
        color_hex TEXT NOT NULL,
        activo INTEGER NOT NULL DEFAULT 1
    );

    CREATE TABLE tareas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        codigo TEXT NOT NULL UNIQUE,
        titulo TEXT NOT NULL,
        descripcion TEXT,
        tipo_tarea TEXT NOT NULL DEFAULT 'AREA',
        columna_id INTEGER NOT NULL,
        prioridad TEXT NOT NULL DEFAULT 'MEDIA',
        creador_id INTEGER NOT NULL,
        responsable_id INTEGER NOT NULL,
        requiere_aprobacion INTEGER NOT NULL DEFAULT 0,
        revisor_id INTEGER,
        fecha_limite TEXT,
        fecha_finalizada TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        FOREIGN KEY (columna_id) REFERENCES tableros_columnas(id),
        FOREIGN KEY (creador_id) REFERENCES usuarios(id),
        FOREIGN KEY (responsable_id) REFERENCES usuarios(id)
    );

    CREATE TABLE subtareas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tarea_id INTEGER NOT NULL,
        titulo TEXT NOT NULL,
        completada INTEGER NOT NULL DEFAULT 0,
        orden INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        FOREIGN KEY (tarea_id) REFERENCES tareas(id) ON DELETE CASCADE
    );

    CREATE TABLE auditoria_eventos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        usuario_id INTEGER,
        entidad_tipo TEXT NOT NULL,
        entidad_id INTEGER NOT NULL,
        accion TEXT NOT NULL,
        estado_anterior TEXT,
        estado_nuevo TEXT,
        detalles_json TEXT,
        ip_address TEXT,
        created_at TEXT NOT NULL
    );
");

Database::setConnection($pdo);

// -----------------------------------------------------------------------------
// 2. Insertar Fixtures (Columnas y Usuarios)
// -----------------------------------------------------------------------------
$pdo->exec("
    INSERT INTO tableros_columnas (id, nombre, orden, tipo_estado, color_hex, activo) VALUES
    (1, 'Pendiente', 1, 'PENDIENTE', '#64748b', 1),
    (2, 'En proceso', 2, 'EN_PROCESO', '#2563eb', 1),
    (3, 'Bloqueada', 3, 'BLOQUEADA', '#e11d48', 1),
    (4, 'En revisión', 4, 'EN_REVISION', '#d97706', 1),
    (5, 'Finalizada', 5, 'FINALIZADA', '#059669', 1);
");

$hashSeguro = password_hash('Planeamiento2026!', PASSWORD_BCRYPT, ['cost' => 10]);
$now = gmdate('Y-m-d H:i:s');

$usuariosSeed = [
    [1, 'ASIST-01', 'Marco Chumbes', 'mchumbes@planeamiento.tal', $hashSeguro, User::ROL_ASISTENTE, 'Asistente de Información 1', 1],
    [2, 'ASIST-02', 'Asistente Dos', 'asistente2@planeamiento.tal', $hashSeguro, User::ROL_ASISTENTE, 'Asistente de Información 2', 1],
    [3, 'ASIST-03', 'Asistente Tres', 'asistente3@planeamiento.tal', $hashSeguro, User::ROL_ASISTENTE, 'Asistente de Información 3', 1],
    [4, 'JEFE-PER', 'Jefe de Personal', 'jefe.personal@planeamiento.tal', $hashSeguro, User::ROL_JEFE_PERSONAL, 'Jefe de Personal', 1],
    [5, 'JEFE-PLA', 'Jefe de Planeamiento', 'jefe.planeamiento@planeamiento.tal', $hashSeguro, User::ROL_JEFE_PLANEAMIENTO, 'Jefe de Planeamiento', 1],
];

$stmt = $pdo->prepare("
    INSERT INTO usuarios (id, codigo, nombre, email, password_hash, rol, cargo, activo, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
foreach ($usuariosSeed as $u) {
    $stmt->execute([$u[0], $u[1], $u[2], $u[3], $u[4], $u[5], $u[6], $u[7], $now, $now]);
}

// Crear sesiones
$tokenAsistente1 = Session::create(1, '127.0.0.1', 'CLI-Test');
$tokenAsistente2 = Session::create(2, '127.0.0.1', 'CLI-Test');
$tokenJefe = Session::create(5, '127.0.0.1', 'CLI-Test');

$userAsistente1 = User::formatPublic(User::findById(1));
$userAsistente2 = User::formatPublic(User::findById(2));
$userJefe = User::formatPublic(User::findById(5));

// Helpers de aserción
$totalTests = 0;
$passedTests = 0;

function testAssert(string $nombre, bool $condicion, string $detalle = ''): void {
    global $totalTests, $passedTests;
    $totalTests++;
    if ($condicion) {
        $passedTests++;
        echo "  [PASS] Test {$totalTests}: {$nombre}\n";
    } else {
        echo "  [FAIL] Test {$totalTests}: {$nombre} - ERROR: {$detalle}\n";
    }
}

function invokeCapture(callable $fn): array {
    ob_start();
    $fn();
    $raw = ob_get_clean();
    $json = json_decode($raw, true) ?? [];
    return [
        'raw'    => $raw,
        'json'   => $json,
        'status' => Response::$lastStatusCode
    ];
}

$taskController = new TaskController();

// =============================================================================
// SUITE DE PRUEBAS
// =============================================================================

// T01: Sin autenticación -> 401 Unauthorized
$res = invokeCapture(function () use ($taskController) {
    $taskController->index(['query' => []]);
});
testAssert("T01: GET /api/tareas sin usuario retorna 401 Unauthorized", $res['status'] === 401);

// T02: Asistente 1 crea tarea PERSONAL para sí mismo (RF-03, RN-01)
$reqCrearPersonal = [
    'user' => $userAsistente1,
    'body' => [
        'titulo'      => 'Organizar archivos personales de inducción',
        'descripcion' => 'Revisión interna privada',
        'tipo_tarea'  => 'PERSONAL',
        'prioridad'   => 'MEDIA'
    ]
];
$res = invokeCapture(function () use ($taskController, $reqCrearPersonal) {
    $taskController->create($reqCrearPersonal);
});
$tareaPersonalAsist1 = $res['json']['data']['tarea'] ?? [];
testAssert(
    "T02: Asistente 1 crea tarea PERSONAL -> 201 Created con responsable = 1 y código correlativo",
    $res['status'] === 201 &&
    ($tareaPersonalAsist1['responsable_id'] ?? 0) === 1 &&
    ($tareaPersonalAsist1['creador_id'] ?? 0) === 1 &&
    str_starts_with($tareaPersonalAsist1['codigo'] ?? '', 'TAR-')
);

// T03 (Negativo RF-05, RN-03): Asistente 1 intenta crear tarea asignándosela a Asistente 2 -> 403 Forbidden
$reqCrearAsignacionIlegal = [
    'user' => $userAsistente1,
    'body' => [
        'titulo'         => 'Intento de delegar reporte a otro asistente',
        'tipo_tarea'     => 'AREA',
        'responsable_id' => 2 // Asistente 2
    ]
];
$res = invokeCapture(function () use ($taskController, $reqCrearAsignacionIlegal) {
    $taskController->create($reqCrearAsignacionIlegal);
});
testAssert(
    "T03: [Seguridad RN-03] Asistente 1 intenta asignar tarea a Asistente 2 -> Rechazado 403 (ERR_FORBIDDEN_ASSIGNMENT)",
    $res['status'] === 403 && ($res['json']['error']['code'] ?? '') === 'ERR_FORBIDDEN_ASSIGNMENT'
);

// T04: Asistente 1 crea tarea oficial de área (RF-04) sin enviar responsable_id -> Se autoasigna
$reqCrearAreaAsist1 = [
    'user' => $userAsistente1,
    'body' => [
        'titulo'       => 'Consolidación semanal de materias primas',
        'tipo_tarea'   => 'AREA',
        'prioridad'    => 'ALTA',
        'fecha_limite' => '2026-10-20'
    ]
];
$res = invokeCapture(function () use ($taskController, $reqCrearAreaAsist1) {
    $taskController->create($reqCrearAreaAsist1);
});
$tareaAreaAsist1 = $res['json']['data']['tarea'] ?? [];
testAssert(
    "T04: Asistente 1 crea tarea AREA -> 201 Created y responsable autoasignado a sí mismo",
    $res['status'] === 201 &&
    ($tareaAreaAsist1['tipo_tarea'] ?? '') === 'AREA' &&
    ($tareaAreaAsist1['responsable_id'] ?? 0) === 1
);

// T05: Jefe de Planeamiento crea tarea AREA asignada a Asistente 2 (RF-06, RN-04)
$reqCrearJefeAsignada = [
    'user' => $userJefe,
    'body' => [
        'titulo'              => 'Cierre mensual de inventarios generales',
        'tipo_tarea'          => 'AREA',
        'prioridad'           => 'URGENTE',
        'responsable_id'      => 2, // Asistente 2
        'requiere_aprobacion' => 1,
        'revisor_id'          => 5
    ]
];
$res = invokeCapture(function () use ($taskController, $reqCrearJefeAsignada) {
    $taskController->create($reqCrearJefeAsignada);
});
$tareaJefeAsist2 = $res['json']['data']['tarea'] ?? [];
testAssert(
    "T05: [Regla RN-04] Jefe asigna tarea AREA a Asistente 2 -> 201 Created con creador=5 y responsable=2",
    $res['status'] === 201 &&
    ($tareaJefeAsist2['creador_id'] ?? 0) === 5 &&
    ($tareaJefeAsist2['responsable_id'] ?? 0) === 2 &&
    ($tareaJefeAsist2['requiere_aprobacion'] ?? false) === true
);

// T06: Jefe intenta asignar a un usuario inexistente (ID 999)
$reqCrearJefeInexistente = [
    'user' => $userJefe,
    'body' => [
        'titulo'         => 'Tarea huérfana',
        'responsable_id' => 999
    ]
];
$res = invokeCapture(function () use ($taskController, $reqCrearJefeInexistente) {
    $taskController->create($reqCrearJefeInexistente);
});
testAssert(
    "T06: Jefe intenta asignar a ID inexistente -> 403 Forbidden (ERR_ASSIGNEE_NOT_FOUND)",
    $res['status'] === 403 && ($res['json']['error']['code'] ?? '') === 'ERR_ASSIGNEE_NOT_FOUND'
);

// T07 (Alcance Servidor): Asistente 1 lista tareas (RF-07, RN-01, RN-02, RN-05)
$res = invokeCapture(function () use ($taskController, $userAsistente1) {
    $taskController->index(['user' => $userAsistente1, 'query' => []]);
});
$tareasAsist1 = $res['json']['data']['tareas'] ?? [];
// Debe ver exactamente 2 tareas (suya personal y suya de área), NO la de Asistente 2
$idsAsist1 = array_column($tareasAsist1, 'id');
testAssert(
    "T07: [Alcance RF-07] Asistente 1 lista tareas y solo recibe las propias (2 tareas, sin ver la de Asistente 2)",
    $res['status'] === 200 && count($tareasAsist1) === 2 && !in_array($tareaJefeAsist2['id'], $idsAsist1, true)
);

// T08 (Alcance Servidor Jefe): Jefe lista tareas
$res = invokeCapture(function () use ($taskController, $userJefe) {
    $taskController->index(['user' => $userJefe, 'query' => []]);
});
$tareasJefe = $res['json']['data']['tareas'] ?? [];
// Debe ver las 2 tareas AREA del equipo (T04 y T05), pero NO la tarea PERSONAL privada de Asistente 1 (RN-01)
$idsJefe = array_column($tareasJefe, 'id');
testAssert(
    "T08: [Alcance Jefatura RN-01] Jefe lista tareas y ve las del ÁREA del equipo, excluyendo tareas personales privadas",
    $res['status'] === 200 &&
    in_array($tareaAreaAsist1['id'], $idsJefe, true) &&
    in_array($tareaJefeAsist2['id'], $idsJefe, true) &&
    !in_array($tareaPersonalAsist1['id'], $idsJefe, true)
);

// T09 (Detalle positivo): Asistente 1 consulta su propia tarea de área
$res = invokeCapture(function () use ($taskController, $userAsistente1, $tareaAreaAsist1) {
    $taskController->show([
        'user'   => $userAsistente1,
        'params' => ['id' => (string)$tareaAreaAsist1['id']]
    ]);
});
testAssert(
    "T09: Asistente 1 consulta su tarea (/api/tareas/{id}) -> 200 OK con datos completos",
    $res['status'] === 200 && ($res['json']['data']['tarea']['id'] ?? 0) === $tareaAreaAsist1['id']
);

// T10 (Seguridad / Anti-IDOR negativo): Asistente 1 intenta consultar la tarea de Asistente 2
$res = invokeCapture(function () use ($taskController, $userAsistente1, $tareaJefeAsist2) {
    $taskController->show([
        'user'   => $userAsistente1,
        'params' => ['id' => (string)$tareaJefeAsist2['id']]
    ]);
});
testAssert(
    "T10: [Seguridad Anti-IDOR RN-02] Asistente 1 intenta ver tarea de Asistente 2 -> Rechazado 403 Forbidden",
    $res['status'] === 403 && ($res['json']['error']['code'] ?? '') === 'ERR_FORBIDDEN_TASK_ACCESS'
);

// T11 (Supervisión Jefatura): Jefe consulta la tarea de área de Asistente 2
$res = invokeCapture(function () use ($taskController, $userJefe, $tareaJefeAsist2) {
    $taskController->show([
        'user'   => $userJefe,
        'params' => ['id' => (string)$tareaJefeAsist2['id']]
    ]);
});
testAssert(
    "T11: Jefe consulta tarea del área de Asistente 2 -> 200 OK (Supervisión oficial autorizada)",
    $res['status'] === 200 && ($res['json']['data']['tarea']['id'] ?? 0) === $tareaJefeAsist2['id']
);

// T12 (Privacidad de Tareas Personales RN-01): Jefe intenta ver tarea personal de Asistente 1
$res = invokeCapture(function () use ($taskController, $userJefe, $tareaPersonalAsist1) {
    $taskController->show([
        'user'   => $userJefe,
        'params' => ['id' => (string)$tareaPersonalAsist1['id']]
    ]);
});
testAssert(
    "T12: [Privacidad RN-01] Jefe intenta ver tarea personal de Asistente 1 -> 403 Forbidden",
    $res['status'] === 403 && ($res['json']['error']['code'] ?? '') === 'ERR_FORBIDDEN_TASK_ACCESS'
);

// T13: Consulta ID inexistente -> 404 Not Found
$res = invokeCapture(function () use ($taskController, $userJefe) {
    $taskController->show([
        'user'   => $userJefe,
        'params' => ['id' => '999']
    ]);
});
testAssert("T13: GET /api/tareas/999 inexistente -> 404 Not Found", $res['status'] === 404);

// T14 (Edición positiva): Asistente 1 edita título y prioridad de su tarea
$res = invokeCapture(function () use ($taskController, $userAsistente1, $tareaAreaAsist1) {
    $taskController->update([
        'user'   => $userAsistente1,
        'params' => ['id' => (string)$tareaAreaAsist1['id']],
        'body'   => [
            'titulo'    => 'Consolidación semanal de materias primas - Actualizada',
            'prioridad' => 'URGENTE'
        ]
    ]);
});
testAssert(
    "T14: Asistente 1 actualiza título y prioridad de su tarea -> 200 OK",
    $res['status'] === 200 &&
    ($res['json']['data']['tarea']['titulo'] ?? '') === 'Consolidación semanal de materias primas - Actualizada' &&
    ($res['json']['data']['tarea']['prioridad'] ?? '') === 'URGENTE'
);

// T15 (Negativo RN-03): Asistente 1 intenta reasignar su tarea a Asistente 2
$res = invokeCapture(function () use ($taskController, $userAsistente1, $tareaAreaAsist1) {
    $taskController->update([
        'user'   => $userAsistente1,
        'params' => ['id' => (string)$tareaAreaAsist1['id']],
        'body'   => [
            'responsable_id' => 2 // Intento de transferir
        ]
    ]);
});
testAssert(
    "T15: [Seguridad RN-03] Asistente 1 intenta reasignar tarea a compañero -> 403 Forbidden (ERR_FORBIDDEN_REASSIGNMENT)",
    $res['status'] === 403 && ($res['json']['error']['code'] ?? '') === 'ERR_FORBIDDEN_REASSIGNMENT'
);

// T16 (Reasignación autorizada RF-06, RN-04): Jefe reasigna la tarea de Asistente 2 a Asistente 1
$res = invokeCapture(function () use ($taskController, $userJefe, $tareaJefeAsist2) {
    $taskController->update([
        'user'   => $userJefe,
        'params' => ['id' => (string)$tareaJefeAsist2['id']],
        'body'   => [
            'responsable_id' => 1 // Reasignar a Asistente 1
        ]
    ]);
});
testAssert(
    "T16: [Reasignación Jefatura RN-04] Jefe reasigna tarea a Asistente 1 -> 200 OK",
    $res['status'] === 200 && ($res['json']['data']['tarea']['responsable_id'] ?? 0) === 1
);

// T17: Asistente 1 ahora ve en su lista la tarea recién reasignada (Criterio de Aceptación 4)
$res = invokeCapture(function () use ($taskController, $userAsistente1) {
    $taskController->index(['user' => $userAsistente1, 'query' => []]);
});
$tareasRevisadasAsist1 = $res['json']['data']['tareas'] ?? [];
$idsRevisados = array_column($tareasRevisadasAsist1, 'id');
testAssert(
    "T17: [Criterio 4 MVP] Tarea reasignada por jefe aparece inmediatamente en la vista de Asistente 1 (total 3)",
    in_array($tareaJefeAsist2['id'], $idsRevisados, true) && count($tareasRevisadasAsist1) === 3
);

// T18 (Validación creación): Título vacío -> 422 Unprocessable Entity
$res = invokeCapture(function () use ($taskController, $userAsistente1) {
    $taskController->create([
        'user' => $userAsistente1,
        'body' => ['titulo' => '   ']
    ]);
});
testAssert("T18: POST /api/tareas con título vacío retorna 422 Unprocessable Entity", $res['status'] === 422);

// T19 (Validación creación): Prioridad inválida -> 422 Unprocessable Entity
$res = invokeCapture(function () use ($taskController, $userAsistente1) {
    $taskController->create([
        'user' => $userAsistente1,
        'body' => [
            'titulo'    => 'Prueba prioridad',
            'prioridad' => 'INVENTADA'
        ]
    ]);
});
testAssert("T19: POST /api/tareas con prioridad inválida retorna 422 Unprocessable Entity", $res['status'] === 422);

// T20 (Eliminación no autorizada): Asistente 2 intenta eliminar tarea creada por Jefe
$res = invokeCapture(function () use ($taskController, $userAsistente2, $tareaJefeAsist2) {
    $taskController->delete([
        'user'   => $userAsistente2,
        'params' => ['id' => (string)$tareaJefeAsist2['id']]
    ]);
});
testAssert(
    "T20: Asistente 2 intenta eliminar tarea que no creó -> 403 Forbidden (ERR_FORBIDDEN_ACTION)",
    $res['status'] === 403
);

// T21 (Eliminación autorizada): Asistente 1 elimina su propia tarea personal
$res = invokeCapture(function () use ($taskController, $userAsistente1, $tareaPersonalAsist1) {
    $taskController->delete([
        'user'   => $userAsistente1,
        'params' => ['id' => (string)$tareaPersonalAsist1['id']]
    ]);
});
testAssert(
    "T21: Creador elimina su tarea -> 200 OK y eliminada exitosamente",
    $res['status'] === 200
);

// T22 (Filtros en listado): Jefe filtra tareas por ?tipo=AREA
$res = invokeCapture(function () use ($taskController, $userJefe) {
    $taskController->index([
        'user'  => $userJefe,
        'query' => ['tipo' => 'AREA']
    ]);
});
$tareasFiltradas = $res['json']['data']['tareas'] ?? [];
$todosArea = true;
foreach ($tareasFiltradas as $t) {
    if ($t['tipo_tarea'] !== 'AREA') {
        $todosArea = false;
    }
}
testAssert(
    "T22: Jefe filtra por ?tipo=AREA -> Todas las tareas retornadas son de tipo AREA",
    $res['status'] === 200 && count($tareasFiltradas) >= 2 && $todosArea
);

// =============================================================================
// PRUEBAS DE ENRUTAMIENTO HTTP Y DISPATCH DEL ROUTER (INTEGRACIÓN)
// =============================================================================

$router = new Router();
$router->get('/api/tareas', [TaskController::class, 'index'], [AuthMiddleware::class]);
$router->get('/api/tareas/{id}', [TaskController::class, 'show'], [AuthMiddleware::class]);
$router->post('/api/tareas', [TaskController::class, 'create'], [AuthMiddleware::class]);
$router->put('/api/tareas/{id}', [TaskController::class, 'update'], [AuthMiddleware::class]);
$router->delete('/api/tareas/{id}', [TaskController::class, 'delete'], [AuthMiddleware::class]);

// T23: Dispatch GET /api/tareas con token de Asistente 1
$_COOKIE['auth_token'] = $tokenAsistente1;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/tareas';

$res = invokeCapture(function () use ($router) {
    $router->dispatch('GET', '/api/tareas');
});
testAssert(
    "T23: Router dispatch GET /api/tareas autenticado -> 200 OK y tareas de su alcance",
    $res['status'] === 200 && is_array($res['json']['data']['tareas'] ?? null)
);

// T24: Dispatch GET /api/tareas/1 sin token -> 401 Unauthorized
$_COOKIE = [];
$_SERVER['HTTP_AUTHORIZATION'] = '';
$res = invokeCapture(function () use ($router) {
    $router->dispatch('GET', '/api/tareas/1');
});
testAssert(
    "T24: Router dispatch GET /api/tareas/1 sin sesión -> 401 Unauthorized",
    $res['status'] === 401
);

// -----------------------------------------------------------------------------
// Resumen Final
// -----------------------------------------------------------------------------
echo "\n=== RESUMEN DE PRUEBAS MVP-03 ===\n";
echo "Total ejecutadas: {$totalTests}\n";
echo "Aprobadas:        {$passedTests}\n";
echo "Fallidas:         " . ($totalTests - $passedTests) . "\n";

if ($passedTests === $totalTests) {
    echo "\n>>> RESULTADO: TODAS LAS PRUEBAS DE MVP-03 PASARON EXITOSAMENTE (100% PASS) <<<\n";
    exit(0);
} else {
    echo "\n>>> RESULTADO: EXISTEN PRUEBAS FALLIDAS EN MVP-03 <<<\n";
    exit(1);
}
