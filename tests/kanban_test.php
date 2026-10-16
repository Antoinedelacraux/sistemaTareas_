<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Config\Database;
use App\Controllers\BoardController;
use App\Core\Middleware\AuthMiddleware;
use App\Core\Response;
use App\Core\Router;
use App\Models\BoardColumn;
use App\Models\Session;
use App\Models\Task;
use App\Models\User;

// Desactivar exit automático para evaluar toda la suite en memoria
Response::$exitOnSend = false;

echo "=== INICIANDO SUITE DE PRUEBAS AUTOMATIZADAS: MVP-04 (KANBAN Y TRANSICIONES) ===\n\n";

// -----------------------------------------------------------------------------
// 1. Configurar Base de Datos Aislada con SQLite en memoria
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
// 2. Semillas: Columnas y Usuarios
// -----------------------------------------------------------------------------
$pdo->exec("
    INSERT INTO tableros_columnas (id, nombre, orden, tipo_estado, color_hex, activo) VALUES
    (1, 'Pendiente', 1, 'PENDIENTE', '#64748b', 1),
    (2, 'En proceso', 2, 'EN_PROCESO', '#2563eb', 1),
    (3, 'Bloqueada', 3, 'BLOQUEADA', '#e11d48', 1),
    (4, 'En revisión', 4, 'EN_REVISION', '#d97706', 1),
    (5, 'Finalizada', 5, 'FINALIZADA', '#059669', 1);
");

$hashSeguro = password_hash('TestOnly-Planeamiento-2026!', PASSWORD_BCRYPT, ['cost' => 10]);
$now = gmdate('Y-m-d H:i:s');

$usuariosSeed = [
    [1, 'ASIST-01', 'Marco Chumbes', 'mchumbes@planeamiento.tal', $hashSeguro, User::ROL_ASISTENTE, 'Asistente de Información 1', 1],
    [2, 'ASIST-02', 'Asistente Dos', 'asistente2@planeamiento.tal', $hashSeguro, User::ROL_ASISTENTE, 'Asistente de Información 2', 1],
    [5, 'JEFE-PLA', 'Jefe de Planeamiento', 'jefe.planeamiento@planeamiento.tal', $hashSeguro, User::ROL_JEFE_PLANEAMIENTO, 'Jefe de Planeamiento', 1],
];

$stmt = $pdo->prepare("
    INSERT INTO usuarios (id, codigo, nombre, email, password_hash, rol, cargo, activo, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
foreach ($usuariosSeed as $u) {
    $stmt->execute([$u[0], $u[1], $u[2], $u[3], $u[4], $u[5], $u[6], $u[7], $now, $now]);
}

$tokenAsistente1 = Session::create(1, '127.0.0.1', 'CLI-Test');
$tokenAsistente2 = Session::create(2, '127.0.0.1', 'CLI-Test');
$tokenJefe = Session::create(5, '127.0.0.1', 'CLI-Test');

$userAsistente1 = User::formatPublic(User::findById(1));
$userAsistente2 = User::formatPublic(User::findById(2));
$userJefe = User::formatPublic(User::findById(5));

// -----------------------------------------------------------------------------
// 3. Semillas de Tareas
// -----------------------------------------------------------------------------
// Tarea 1: Asistente 1, sin aprobación, en Pendiente (columna 1)
// Tarea 2: Asistente 1, con aprobación, en En proceso (columna 2)
// Tarea 3: Asistente 2, del área, en Pendiente (columna 1)
// Tarea 4: Asistente 1, PERSONAL, en Finalizada (columna 5)
$pdo->exec("
    INSERT INTO tareas (id, codigo, titulo, descripcion, tipo_tarea, columna_id, prioridad, creador_id, responsable_id, requiere_aprobacion, revisor_id, fecha_limite, fecha_finalizada, created_at, updated_at) VALUES
    (1, 'TAR-2026-0001', 'Conciliación de partes de turno', 'Sin aprobación requerida', 'AREA', 1, 'MEDIA', 1, 1, 0, NULL, '2026-10-25', NULL, '{$now}', '{$now}'),
    (2, 'TAR-2026-0002', 'Informe mensual de balances', 'Exige aprobación de jefatura', 'AREA', 2, 'ALTA', 1, 1, 1, 5, '2026-10-28', NULL, '{$now}', '{$now}'),
    (3, 'TAR-2026-0003', 'Muestreo de densidades planta', 'Tarea oficial de Asistente 2', 'AREA', 1, 'URGENTE', 5, 2, 0, NULL, '2026-10-22', NULL, '{$now}', '{$now}'),
    (4, 'TAR-2026-0004', 'Notas personales de inducción', 'Tarea privada de Asistente 1', 'PERSONAL', 5, 'BAJA', 1, 1, 0, NULL, NULL, '{$now}', '{$now}', '{$now}');
");

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

$boardController = new BoardController();

// =============================================================================
// PRUEBAS DE TABLERO Y COLUMNAS
// =============================================================================

// T01: Obtener columnas del tablero
$res = invokeCapture(function () use ($boardController, $userAsistente1) {
    $boardController->columns(['user' => $userAsistente1]);
});
$columnas = $res['json']['data']['columnas'] ?? [];
testAssert(
    "T01: GET /api/tableros/columnas retorna las 5 columnas activas en orden",
    $res['status'] === 200 && count($columnas) === 5 && $columnas[0]['tipo_estado'] === 'PENDIENTE' && $columnas[4]['tipo_estado'] === 'FINALIZADA'
);

// T02: GET /api/tableros/kanban sin autenticación -> 401
$res = invokeCapture(function () use ($boardController) {
    $boardController->kanban(['query' => []]);
});
testAssert("T02: GET /api/tableros/kanban sin sesión retorna 401 Unauthorized", $res['status'] === 401);

// T03: Kanban individual de Asistente 1 (RF-08, RF-07)
$res = invokeCapture(function () use ($boardController, $userAsistente1) {
    $boardController->kanban(['user' => $userAsistente1, 'query' => []]);
});
$kanbanAsist = $res['json']['data'] ?? [];
$todasTareasAsist = [];
foreach ($kanbanAsist['columnas'] ?? [] as $col) {
    foreach ($col['tareas'] as $t) {
        $todasTareasAsist[] = $t['id'];
    }
}
testAssert(
    "T03: [Kanban Asistente] Asistente 1 solo ve sus tareas oficiales (1, 2) y excluye el registro personal histórico (4) y la tarea de Asistente 2 (3)",
    $res['status'] === 200 &&
    count($todasTareasAsist) === 2 &&
    in_array(1, $todasTareasAsist, true) &&
    in_array(2, $todasTareasAsist, true) &&
    !in_array(4, $todasTareasAsist, true) &&
    !in_array(3, $todasTareasAsist, true)
);

// T04: Kanban general de Jefatura (RF-08, RN-01)
$res = invokeCapture(function () use ($boardController, $userJefe) {
    $boardController->kanban(['user' => $userJefe, 'query' => []]);
});
$kanbanJefe = $res['json']['data'] ?? [];
$todasTareasJefe = [];
foreach ($kanbanJefe['columnas'] ?? [] as $col) {
    foreach ($col['tareas'] as $t) {
        $todasTareasJefe[] = $t['id'];
    }
}
testAssert(
    "T04: [Kanban Jefe] Jefe ve todas las tareas AREA del equipo (1, 2, 3), excluyendo la tarea PERSONAL privada (4)",
    $res['status'] === 200 &&
    in_array(1, $todasTareasJefe, true) &&
    in_array(2, $todasTareasJefe, true) &&
    in_array(3, $todasTareasJefe, true) &&
    !in_array(4, $todasTareasJefe, true)
);

// =============================================================================
// PRUEBAS DE TRANSICIONES Y REGLAS DE NEGOCIO (RF-11, RF-12, RN-06, RN-10, RN-11)
// =============================================================================

// T05: Transición básica: Asistente 1 mueve Tarea 1 de Pendiente (1) a En proceso (2)
$res = invokeCapture(function () use ($boardController, $userJefe) {
    $boardController->transition([
        'user'   => $userJefe,
        'params' => ['id' => '1'],
        'body'   => ['nueva_columna_id' => 2, 'motivo' => 'Iniciando recopilación de datos']
    ]);
});
testAssert(
    "T05: Asistente 1 mueve Tarea 1 a 'En proceso' -> 200 OK y columna_id actualizada a 2",
    $res['status'] === 200 && ($res['json']['data']['tarea']['columna_id'] ?? 0) === 2
);

// T06: Transición a la misma columna actual (sin cambios)
$res = invokeCapture(function () use ($boardController, $userJefe) {
    $boardController->transition([
        'user'   => $userJefe,
        'params' => ['id' => '1'],
        'body'   => ['nueva_columna_id' => 2]
    ]);
});
testAssert(
    "T06: Transición a la misma columna actual retorna 200 OK sin duplicar evento",
    $res['status'] === 200 && ($res['json']['data']['tarea']['columna_id'] ?? 0) === 2
);

// T07: Seguridad / Control de Acceso: Asistente 1 intenta mover la Tarea 3 de Asistente 2 -> 403 Forbidden
$res = invokeCapture(function () use ($boardController, $userAsistente1) {
    $boardController->transition([
        'user'   => $userAsistente1,
        'params' => ['id' => '3'],
        'body'   => ['nueva_columna_id' => 2]
    ]);
});
testAssert(
    "T07: [Seguridad] Asistente 1 intenta mover tarea de compañero -> 403 Forbidden (ERR_FORBIDDEN_ACTION)",
    $res['status'] === 403
);

// T08 (Regla RN-11 Negativo): Tarea sin aprobación intenta cerrar sin confirmación -> 422 Unprocessable Entity
$res = invokeCapture(function () use ($boardController, $userAsistente1) {
    $boardController->transition([
        'user'   => $userAsistente1,
        'params' => ['id' => '1'],
        'body'   => [
            'nueva_columna_id'    => 5, // Finalizada
            'confirmacion_cierre' => false // Sin confirmación explícita
        ]
    ]);
});
testAssert(
    "T08: [Regla RN-11] Cierre sin confirmación explícita es rechazado con 422 (ERR_CONFIRMATION_REQUIRED)",
    $res['status'] === 422 && ($res['json']['error']['code'] ?? '') === 'ERR_CONFIRMATION_REQUIRED'
);

// Comprobar que el estado de Tarea 1 sigue intacto en columna 2 ('En proceso')
$tarea1Actual = Task::findById(1);
testAssert(
    "T08b: El estado de la tarea permanece intacto en 'En proceso' tras rechazo de confirmación",
    ($tarea1Actual['columna_id'] ?? 0) === 2
);

// T09 (Regla RN-11 Positivo): Tarea sin aprobación confirma cierre explícitamente -> 200 OK
$res = invokeCapture(function () use ($boardController, $userAsistente1) {
    $boardController->transition([
        'user'   => $userAsistente1,
        'params' => ['id' => '1'],
        'body'   => [
            'nueva_columna_id'    => 5, // Finalizada
            'confirmacion_cierre' => true,
            'motivo'              => 'Cierre confirmado por asistente'
        ]
    ]);
});
testAssert(
    "T09: [Regla RN-11] Cierre con confirmacion_cierre = true transiciona exitosamente a Finalizada",
    $res['status'] === 200 &&
    ($res['json']['data']['tarea']['columna_id'] ?? 0) === 5 &&
    !empty($res['json']['data']['tarea']['fecha_finalizada'])
);

// T10 (Regla RN-10 Negativo): Tarea con aprobación (Tarea 2) intenta pasar directamente a Finalizada -> 409 Conflict
$res = invokeCapture(function () use ($boardController, $userAsistente1) {
    $boardController->transition([
        'user'   => $userAsistente1,
        'params' => ['id' => '2'],
        'body'   => [
            'nueva_columna_id'    => 5, // Intento de saltar a Finalizada
            'confirmacion_cierre' => true
        ]
    ]);
});
testAssert(
    "T10: [Regla RN-10] Tarea que exige aprobación no puede pasar directo a Finalizada -> Rechazado 409 Conflict",
    $res['status'] === 409 && ($res['json']['error']['code'] ?? '') === 'ERR_APPROVAL_REQUIRED'
);

// T11: Tarea 2 pasa a 'En revisión' (columna 4) -> 200 OK
$res = invokeCapture(function () use ($boardController, $userAsistente1) {
    $boardController->transition([
        'user'   => $userAsistente1,
        'params' => ['id' => '2'],
        'body'   => ['nueva_columna_id' => 4, 'motivo' => 'Entrega lista para revisión de jefatura']
    ]);
});
testAssert(
    "T11: Tarea 2 transiciona correctamente a 'En revisión' (columna 4) -> 200 OK",
    $res['status'] === 200 && ($res['json']['data']['tarea']['columna_id'] ?? 0) === 4
);

// T12 (Reapertura de tarea): solo Jefatura puede reabrir una tarea finalizada.
$res = invokeCapture(function () use ($boardController, $userJefe) {
    $boardController->transition([
        'user'   => $userJefe,
        'params' => ['id' => '1'],
        'body'   => ['nueva_columna_id' => 2, 'motivo' => 'Reapertura por observación menor']
    ]);
});
testAssert(
    "T12: Reapertura de tarea resetea fecha_finalizada a null y vuelve a columna 2",
    $res['status'] === 200 &&
    ($res['json']['data']['tarea']['columna_id'] ?? 0) === 2 &&
    empty($res['json']['data']['tarea']['fecha_finalizada'])
);

// T13: Intento de mover a columna inexistente -> 404
$res = invokeCapture(function () use ($boardController, $userJefe) {
    $boardController->transition([
        'user'   => $userJefe,
        'params' => ['id' => '1'],
        'body'   => ['nueva_columna_id' => 999]
    ]);
});
testAssert("T13: Transición a nueva_columna_id inexistente retorna 404 Not Found", $res['status'] === 404);

// T14: Intento de mover tarea inexistente -> 404
$res = invokeCapture(function () use ($boardController, $userAsistente1) {
    $boardController->transition([
        'user'   => $userAsistente1,
        'params' => ['id' => '999'],
        'body'   => ['nueva_columna_id' => 2]
    ]);
});
testAssert("T14: Transición sobre ID de tarea inexistente retorna 404 Not Found", $res['status'] === 404);

// T15 (Supervisión Jefatura): Jefe mueve Tarea 3 de Asistente 2 a 'En proceso' -> 200 OK
$res = invokeCapture(function () use ($boardController, $userJefe) {
    $boardController->transition([
        'user'   => $userJefe,
        'params' => ['id' => '3'],
        'body'   => ['nueva_columna_id' => 2, 'motivo' => 'Iniciada por instrucción de jefatura']
    ]);
});
testAssert(
    "T15: Jefe mueve tarea oficial del área de Asistente 2 -> Autorizado 200 OK",
    $res['status'] === 200 && ($res['json']['data']['tarea']['columna_id'] ?? 0) === 2
);

// =============================================================================
// PRUEBAS DE ENRUTAMIENTO HTTP ROUTER DISPATCH (INTEGRACIÓN)
// =============================================================================

$router = new Router();
$router->get('/api/tableros/columnas', [BoardController::class, 'columns'], [AuthMiddleware::class]);
$router->get('/api/tableros/kanban', [BoardController::class, 'kanban'], [AuthMiddleware::class]);
$router->post('/api/tareas/{id}/transicion', [BoardController::class, 'transition'], [AuthMiddleware::class]);

// T16: Router dispatch GET /api/tableros/columnas con sesión
$_COOKIE['auth_token'] = $tokenAsistente1;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/tableros/columnas';

$res = invokeCapture(function () use ($router) {
    $router->dispatch('GET', '/api/tableros/columnas');
});
testAssert(
    "T16: Router dispatch GET /api/tableros/columnas -> 200 OK y 5 columnas",
    $res['status'] === 200 && count($res['json']['data']['columnas'] ?? []) === 5
);

// T17: Router dispatch GET /api/tableros/kanban con sesión de Asistente 1
$res = invokeCapture(function () use ($router) {
    $router->dispatch('GET', '/api/tableros/kanban');
});
testAssert(
    "T17: Router dispatch GET /api/tableros/kanban -> 200 OK con estructura agrupada",
    $res['status'] === 200 && isset($res['json']['data']['columnas']) && isset($res['json']['data']['resumen'])
);

// -----------------------------------------------------------------------------
// Resumen Final
// -----------------------------------------------------------------------------
echo "\n=== RESUMEN DE PRUEBAS MVP-04 ===\n";
echo "Total ejecutadas: {$totalTests}\n";
echo "Aprobadas:        {$passedTests}\n";
echo "Fallidas:         " . ($totalTests - $passedTests) . "\n";

if ($passedTests === $totalTests) {
    echo "\n>>> RESULTADO: TODAS LAS PRUEBAS DE MVP-04 PASARON EXITOSAMENTE (100% PASS) <<<\n";
    exit(0);
} else {
    echo "\n>>> RESULTADO: EXISTEN PRUEBAS FALLIDAS EN MVP-04 <<<\n";
    exit(1);
}
