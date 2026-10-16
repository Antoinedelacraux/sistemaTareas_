<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Config\Database;
use App\Controllers\UserController;
use App\Core\Middleware\AuthMiddleware;
use App\Core\Response;
use App\Core\Router;
use App\Models\Session;
use App\Models\User;

// Desactivar exit automático para ejecutar la suite completa en memoria
Response::$exitOnSend = false;

echo "=== INICIANDO SUITE DE PRUEBAS AUTOMATIZADAS: MVP-02 (USUARIOS, ÁMBITOS Y ASIGNACIÓN) ===\n\n";

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
// 2. Insertar Fixtures de Prueba (Equipo de 5 personas + 1 inactivo)
// -----------------------------------------------------------------------------
$hashSeguro = password_hash('TestOnly-Planeamiento-2026!', PASSWORD_BCRYPT, ['cost' => 10]);
$now = gmdate('Y-m-d H:i:s');

$usuariosSeed = [
    [1, 'ASIST-01', 'Marco Chumbes', 'mchumbes@planeamiento.tal', $hashSeguro, User::ROL_ASISTENTE, 'Asistente de Información 1', 1],
    [2, 'ASIST-02', 'Asistente Dos', 'asistente2@planeamiento.tal', $hashSeguro, User::ROL_ASISTENTE, 'Asistente de Información 2', 1],
    [3, 'ASIST-03', 'Asistente Tres', 'asistente3@planeamiento.tal', $hashSeguro, User::ROL_ASISTENTE, 'Asistente de Información 3', 1],
    [4, 'JEFE-PER', 'Jefe de Personal', 'jefe.personal@planeamiento.tal', $hashSeguro, User::ROL_JEFE_PERSONAL, 'Jefe de Personal', 1],
    [5, 'JEFE-PLA', 'Jefe de Planeamiento', 'jefe.planeamiento@planeamiento.tal', $hashSeguro, User::ROL_JEFE_PLANEAMIENTO, 'Jefe de Planeamiento', 1],
    [6, 'ASIST-INA', 'Asistente Inactivo', 'inactivo@planeamiento.tal', $hashSeguro, User::ROL_ASISTENTE, 'Ex Asistente', 0],
];

$stmt = $pdo->prepare("
    INSERT INTO usuarios (id, codigo, nombre, email, password_hash, rol, cargo, activo, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
foreach ($usuariosSeed as $u) {
    $stmt->execute([$u[0], $u[1], $u[2], $u[3], $u[4], $u[5], $u[6], $u[7], $now, $now]);
}

// Crear sesiones activas
$tokenAsistente = Session::create(1, '127.0.0.1', 'CLI-Test');
$tokenJefe = Session::create(5, '127.0.0.1', 'CLI-Test');

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

// Helper para capturar salida de un controlador o callback
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

$userController = new UserController();

// =============================================================================
// PRUEBAS DE LISTADO Y ÁMBITO (RF-05, RF-06, RN-03, RN-04, RN-05)
// =============================================================================

// T01: Sin autenticación -> 401 Unauthorized
$_COOKIE = [];
$_SERVER['HTTP_AUTHORIZATION'] = '';
$res = invokeCapture(function () use ($userController) {
    $auth = new AuthMiddleware();
    $req = ['query' => []];
    $auth->handle($req);
    if (isset($req['user'])) {
        $userController->index($req);
    }
});
testAssert("T01: GET /api/usuarios sin token retorna 401 Unauthorized", $res['status'] === 401 && ($res['json']['error']['code'] ?? '') === 'ERR_UNAUTHORIZED');

// Preparar contexto autenticado para Asistente 1 (ID 1)
$userAsistente = User::formatPublic(User::findById(1));
$reqAsistente = [
    'user'   => $userAsistente,
    'query'  => [],
    'params' => []
];

// T02: Asistente solo puede verse a sí mismo en el listado (RF-05, RN-03, RN-05)
$res = invokeCapture(function () use ($userController, $reqAsistente) {
    $userController->index($reqAsistente);
});
$usuariosAsist = $res['json']['data']['usuarios'] ?? [];
testAssert(
    "T02: Asistente en GET /api/usuarios recibe únicamente a sí mismo (total = 1)",
    $res['status'] === 200 && count($usuariosAsist) === 1 && $usuariosAsist[0]['id'] === 1 && $usuariosAsist[0]['codigo'] === 'ASIST-01'
);

// T03: Asistente NO ve a otros asistentes ni jefes en el listado
$hayOtros = false;
foreach ($usuariosAsist as $u) {
    if ($u['id'] !== 1) {
        $hayOtros = true;
    }
}
testAssert("T03: Asistente NO expone otros usuarios en la lista de asignación (Anti-Fuga)", !$hayOtros);

// Preparar contexto autenticado para Jefe de Planeamiento (ID 5)
$userJefe = User::formatPublic(User::findById(5));
$reqJefe = [
    'user'   => $userJefe,
    'query'  => [],
    'params' => []
];

// T04: Jefe puede ver a todos los miembros activos (5 usuarios activos) (RF-06, RN-04)
$res = invokeCapture(function () use ($userController, $reqJefe) {
    $userController->index($reqJefe);
});
$usuariosJefe = $res['json']['data']['usuarios'] ?? [];
testAssert(
    "T04: Jefe en GET /api/usuarios recibe a todo el equipo activo (5 usuarios)",
    $res['status'] === 200 && count($usuariosJefe) === 5
);

// T05: Jefe no ve al usuario inactivo por defecto
$hayInactivos = false;
foreach ($usuariosJefe as $u) {
    if (!$u['activo']) {
        $hayInactivos = true;
    }
}
testAssert("T05: Lista de asignación de Jefatura excluye usuarios inactivos por defecto", !$hayInactivos);

// T06: Jefe filtra por rol ASISTENTE (?rol=ASISTENTE)
$reqJefeFiltroRol = $reqJefe;
$reqJefeFiltroRol['query'] = ['rol' => User::ROL_ASISTENTE];
$res = invokeCapture(function () use ($userController, $reqJefeFiltroRol) {
    $userController->index($reqJefeFiltroRol);
});
$asistentesFiltrados = $res['json']['data']['usuarios'] ?? [];
testAssert(
    "T06: Jefe filtra usuarios por ?rol=ASISTENTE y obtiene exactamente 3 asistentes activos",
    $res['status'] === 200 && count($asistentesFiltrados) === 3
);

// T07: Jefe busca por texto (?buscar=Chumbes)
$reqJefeBusqueda = $reqJefe;
$reqJefeBusqueda['query'] = ['buscar' => 'Chumbes'];
$res = invokeCapture(function () use ($userController, $reqJefeBusqueda) {
    $userController->index($reqJefeBusqueda);
});
$busquedaRes = $res['json']['data']['usuarios'] ?? [];
testAssert(
    "T07: Búsqueda textual ?buscar=Chumbes retorna a Marco Chumbes",
    $res['status'] === 200 && count($busquedaRes) === 1 && $busquedaRes[0]['codigo'] === 'ASIST-01'
);

// =============================================================================
// PRUEBAS DE DETALLE Y PROTECCIÓN ANTI-IDOR (RF-07, RN-02, RN-05)
// =============================================================================

// T08: Asistente consulta su propio perfil -> 200 OK
$reqAsistenteShowMe = $reqAsistente;
$reqAsistenteShowMe['params'] = ['id' => '1'];
$res = invokeCapture(function () use ($userController, $reqAsistenteShowMe) {
    $userController->show($reqAsistenteShowMe);
});
$userData = $res['json']['data']['usuario'] ?? [];
testAssert(
    "T08: Asistente 1 consulta su propio perfil (/api/usuarios/1) -> 200 OK",
    $res['status'] === 200 && $userData['id'] === 1 && !isset($userData['password_hash'])
);

// T09: SEGURIDAD / ANTI-IDOR: Asistente 1 intenta consultar perfil de Asistente 2 (/api/usuarios/2) -> 403 Forbidden
$reqAsistenteShowOther = $reqAsistente;
$reqAsistenteShowOther['params'] = ['id' => '2'];
$res = invokeCapture(function () use ($userController, $reqAsistenteShowOther) {
    $userController->show($reqAsistenteShowOther);
});
testAssert(
    "T09: [Seguridad IDOR] Asistente 1 intenta ver perfil de Asistente 2 -> 403 Forbidden",
    $res['status'] === 403 && ($res['json']['error']['code'] ?? '') === 'ERR_FORBIDDEN_USER_ACCESS'
);

// T10: SEGURIDAD / ANTI-IDOR: Asistente 1 intenta consultar perfil de Jefe (/api/usuarios/5) -> 403 Forbidden
$reqAsistenteShowJefe = $reqAsistente;
$reqAsistenteShowJefe['params'] = ['id' => '5'];
$res = invokeCapture(function () use ($userController, $reqAsistenteShowJefe) {
    $userController->show($reqAsistenteShowJefe);
});
testAssert(
    "T10: [Seguridad IDOR] Asistente 1 intenta ver perfil de Jefe -> 403 Forbidden",
    $res['status'] === 403 && ($res['json']['error']['code'] ?? '') === 'ERR_FORBIDDEN_USER_ACCESS'
);

// T11: Jefe consulta perfil de Asistente 1 (/api/usuarios/1) -> 200 OK
$reqJefeShowAsistente = $reqJefe;
$reqJefeShowAsistente['params'] = ['id' => '1'];
$res = invokeCapture(function () use ($userController, $reqJefeShowAsistente) {
    $userController->show($reqJefeShowAsistente);
});
testAssert(
    "T11: Jefe consulta perfil de Asistente 1 (/api/usuarios/1) -> 200 OK",
    $res['status'] === 200 && ($res['json']['data']['usuario']['id'] ?? 0) === 1
);

// T12: Jefe consulta ID inexistente (/api/usuarios/999) -> 404 Not Found
$reqJefeShowNotFound = $reqJefe;
$reqJefeShowNotFound['params'] = ['id' => '999'];
$res = invokeCapture(function () use ($userController, $reqJefeShowNotFound) {
    $userController->show($reqJefeShowNotFound);
});
testAssert(
    "T12: Jefe consulta ID inexistente (/api/usuarios/999) -> 404 Not Found",
    $res['status'] === 404 && ($res['json']['error']['code'] ?? '') === 'ERR_USER_NOT_FOUND'
);

// T13: ID no válido (/api/usuarios/0 o texto) -> 400 Bad Request
$reqInvalidId = $reqJefe;
$reqInvalidId['params'] = ['id' => 'invalid'];
$res = invokeCapture(function () use ($userController, $reqInvalidId) {
    $userController->show($reqInvalidId);
});
testAssert(
    "T13: Parámetro ID no numérico -> 400 Bad Request (ERR_INVALID_ID)",
    $res['status'] === 400 && ($res['json']['error']['code'] ?? '') === 'ERR_INVALID_ID'
);

// =============================================================================
// PRUEBAS DE AUTORIZACIÓN DE ASIGNACIÓN (RF-05, RF-06, RN-03, RN-04)
// =============================================================================

// T14: Asistente intenta asignar a compañero (responsable_id = 2) -> 403 Forbidden
$reqAsignacionIlegal = $reqAsistente;
$reqAsignacionIlegal['body'] = ['responsable_id' => 2];
$res = invokeCapture(function () use ($userController, $reqAsignacionIlegal) {
    $userController->validateAssignment($reqAsignacionIlegal);
});
testAssert(
    "T14: [Regla RN-03] Asistente 1 intenta asignar a Asistente 2 -> Denegado 403 (ERR_FORBIDDEN_ASSIGNMENT)",
    $res['status'] === 403 && ($res['json']['error']['code'] ?? '') === 'ERR_FORBIDDEN_ASSIGNMENT'
);

// T15: Asistente intenta asignar a Jefe (responsable_id = 5) -> 403 Forbidden
$reqAsignacionIlegalJefe = $reqAsistente;
$reqAsignacionIlegalJefe['body'] = ['responsable_id' => 5];
$res = invokeCapture(function () use ($userController, $reqAsignacionIlegalJefe) {
    $userController->validateAssignment($reqAsignacionIlegalJefe);
});
testAssert(
    "T15: [Regla RN-03] Asistente 1 intenta asignar a Jefe -> Denegado 403 (ERR_FORBIDDEN_ASSIGNMENT)",
    $res['status'] === 403 && ($res['json']['error']['code'] ?? '') === 'ERR_FORBIDDEN_ASSIGNMENT'
);

// T16: Asistente se autoasigna (responsable_id = 1) -> 200 OK
$reqAutoAsignacion = $reqAsistente;
$reqAutoAsignacion['body'] = ['responsable_id' => 1];
$res = invokeCapture(function () use ($userController, $reqAutoAsignacion) {
    $userController->validateAssignment($reqAutoAsignacion);
});
testAssert(
    "T16: [Regla RF-05] Asistente 1 se autoasigna (responsable_id = 1) -> Autorizado 200 OK",
    $res['status'] === 200 && ($res['json']['data']['valido'] ?? false) === true
);

// T17: Jefe asigna tarea a Asistente 1 (responsable_id = 1) -> 200 OK (RF-06, RN-04)
$reqJefeAsignaAsistente = $reqJefe;
$reqJefeAsignaAsistente['body'] = ['responsable_id' => 1];
$res = invokeCapture(function () use ($userController, $reqJefeAsignaAsistente) {
    $userController->validateAssignment($reqJefeAsignaAsistente);
});
testAssert(
    "T17: [Regla RN-04] Jefe asigna tarea a Asistente 1 -> Autorizado 200 OK",
    $res['status'] === 200 && ($res['json']['data']['valido'] ?? false) === true
);

// T18: Jefe intenta asignar a usuario inactivo (ID 6) -> 403 Forbidden (ERR_ASSIGNEE_INACTIVE)
$reqJefeAsignaInactivo = $reqJefe;
$reqJefeAsignaInactivo['body'] = ['responsable_id' => 6];
$res = invokeCapture(function () use ($userController, $reqJefeAsignaInactivo) {
    $userController->validateAssignment($reqJefeAsignaInactivo);
});
testAssert(
    "T18: Intento de asignar a usuario inactivo (ID 6) -> Denegado 403 (ERR_ASSIGNEE_INACTIVE)",
    $res['status'] === 403 && ($res['json']['error']['code'] ?? '') === 'ERR_ASSIGNEE_INACTIVE'
);

// T19: Jefe intenta asignar a usuario inexistente (ID 999) -> 403 Forbidden (ERR_ASSIGNEE_NOT_FOUND)
$reqJefeAsignaInexistente = $reqJefe;
$reqJefeAsignaInexistente['body'] = ['responsable_id' => 999];
$res = invokeCapture(function () use ($userController, $reqJefeAsignaInexistente) {
    $userController->validateAssignment($reqJefeAsignaInexistente);
});
testAssert(
    "T19: Intento de asignar a usuario inexistente (ID 999) -> Denegado 403 (ERR_ASSIGNEE_NOT_FOUND)",
    $res['status'] === 403 && ($res['json']['error']['code'] ?? '') === 'ERR_ASSIGNEE_NOT_FOUND'
);

// =============================================================================
// PRUEBAS DE ENRUTAMIENTO HTTP Y DISPATCH DEL ROUTER (INTEGRACIÓN)
// =============================================================================

$router = new Router();
$router->get('/api/usuarios', [UserController::class, 'index'], [AuthMiddleware::class]);
$router->get('/api/usuarios/{id}', [UserController::class, 'show'], [AuthMiddleware::class]);
$router->post('/api/usuarios/validar-asignacion', [UserController::class, 'validateAssignment'], [AuthMiddleware::class]);

// T20: Dispatch GET /api/usuarios con token de asistente en Cookie
$_COOKIE['auth_token'] = $tokenAsistente;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/usuarios';
$_SERVER['HTTP_AUTHORIZATION'] = '';

$res = invokeCapture(function () use ($router) {
    $router->dispatch('GET', '/api/usuarios');
});
testAssert(
    "T20: Router dispatch GET /api/usuarios con sesión activa -> 200 OK y 1 usuario",
    $res['status'] === 200 && count($res['json']['data']['usuarios'] ?? []) === 1,
    "Status: {$res['status']}, Raw: {$res['raw']}"
);

// T21: Dispatch GET /api/usuarios/1 con token de asistente
$_COOKIE['auth_token'] = $tokenAsistente;
$res = invokeCapture(function () use ($router) {
    $router->dispatch('GET', '/api/usuarios/1');
});
testAssert(
    "T21: Router dispatch GET /api/usuarios/1 con sesión de Asistente 1 -> 200 OK",
    $res['status'] === 200 && ($res['json']['data']['usuario']['id'] ?? 0) === 1
);

// T22: Dispatch GET /api/usuarios/2 con token de asistente -> 403 Forbidden por IDOR
$_COOKIE['auth_token'] = $tokenAsistente;
$res = invokeCapture(function () use ($router) {
    $router->dispatch('GET', '/api/usuarios/2');
});
testAssert(
    "T22: Router dispatch GET /api/usuarios/2 con sesión de Asistente 1 -> 403 Forbidden (Anti-IDOR)",
    $res['status'] === 403 && ($res['json']['error']['code'] ?? '') === 'ERR_FORBIDDEN_USER_ACCESS'
);

// T23: Dispatch GET /api/usuarios con token de jefe -> 200 OK y 5 usuarios
$_COOKIE['auth_token'] = $tokenJefe;
$res = invokeCapture(function () use ($router) {
    $router->dispatch('GET', '/api/usuarios');
});
testAssert(
    "T23: Router dispatch GET /api/usuarios con sesión de Jefe -> 200 OK y 5 usuarios",
    $res['status'] === 200 && count($res['json']['data']['usuarios'] ?? []) === 5
);

// -----------------------------------------------------------------------------
// Resumen Final
// -----------------------------------------------------------------------------
echo "\n=== RESUMEN DE PRUEBAS MVP-02 ===\n";
echo "Total ejecutadas: {$totalTests}\n";
echo "Aprobadas:        {$passedTests}\n";
echo "Fallidas:         " . ($totalTests - $passedTests) . "\n";

if ($passedTests === $totalTests) {
    echo "\n>>> RESULTADO: TODAS LAS PRUEBAS DE MVP-02 PASARON EXITOSAMENTE (100% PASS) <<<\n";
    exit(0);
} else {
    echo "\n>>> RESULTADO: EXISTEN PRUEBAS FALLIDAS EN MVP-02 <<<\n";
    exit(1);
}
