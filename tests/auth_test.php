<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Config\Database;
use App\Controllers\AuthController;
use App\Core\Middleware\AuthMiddleware;
use App\Core\Response;
use App\Models\Session;
use App\Models\User;

// Desactivar finalización de proceso para evaluar múltiples endpoints en secuencia
Response::$exitOnSend = false;

echo "=== INICIANDO SUITE DE PRUEBAS AUTOMATIZADAS: MVP-01 (AUTENTICACIÓN Y RBAC) ===\n\n";

// -----------------------------------------------------------------------------
// 1. Configurar Entorno de Pruebas Aislado con SQLite en memoria
// -----------------------------------------------------------------------------
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// Crear tablas equivalentes para pruebas
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

// Inyectar conexión de pruebas
Database::setConnection($pdo);

// Insertar semillas de prueba
$passwordHash = password_hash('TestOnly-Planeamiento-2026!', PASSWORD_BCRYPT);
$now = gmdate('Y-m-d H:i:s');

$pdo->exec("
    INSERT INTO usuarios (id, codigo, nombre, email, password_hash, rol, cargo, activo, created_at, updated_at) VALUES
    (1, 'ASIST-01', 'Marco Chumbes', 'mchumbes@planeamiento.tal', '{$passwordHash}', 'ASISTENTE', 'Asistente de Información 1', 1, '{$now}', '{$now}'),
    (2, 'ASIST-02', 'Asistente Dos', 'asistente2@planeamiento.tal', '{$passwordHash}', 'ASISTENTE', 'Asistente de Información 2', 1, '{$now}', '{$now}'),
    (3, 'ASIST-03', 'Asistente Tres', 'asistente3@planeamiento.tal', '{$passwordHash}', 'ASISTENTE', 'Asistente de Información 3', 1, '{$now}', '{$now}'),
    (4, 'JEFE-PER', 'Jefe de Personal', 'jefe.personal@planeamiento.tal', '{$passwordHash}', 'JEFE_PERSONAL', 'Jefe de Personal', 1, '{$now}', '{$now}'),
    (5, 'JEFE-PLA', 'Jefe de Planeamiento', 'jefe.planeamiento@planeamiento.tal', '{$passwordHash}', 'JEFE_PLANEAMIENTO', 'Jefe del Área de Planeamiento', 1, '{$now}', '{$now}'),
    (6, 'INACT-01', 'Usuario Desactivado', 'inactivo@planeamiento.tal', '{$passwordHash}', 'ASISTENTE', 'Ex Asistente', 0, '{$now}', '{$now}');
");

$passed = 0;
$total = 0;

function assertTest(string $description, bool $condition, ?array $debug = null): void {
    global $passed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$description}\n";
    } else {
        echo "  [FAIL] {$description}\n";
        if ($debug) {
            echo "  DEBUG: " . json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
        exit(1);
    }
}

// Helper para capturar respuestas de controladores
function runController(callable $fn): array {
    ob_start();
    try {
        $fn();
    } catch (\Throwable $e) {
        // En caso de excepción
    }
    $output = ob_get_clean();
    $decoded = json_decode($output, true);
    return [
        'code' => Response::$lastStatusCode,
        'json' => $decoded,
        'raw'  => $output
    ];
}

$auth = new AuthController();

// -----------------------------------------------------------------------------
// Caso 1: Login Exitoso de Asistente
// -----------------------------------------------------------------------------
echo "\n--- Probando Casos Positivos de Autenticación ---\n";

$res1 = runController(function () use ($auth) {
    $auth->login([
        'body' => [
            'email'    => 'mchumbes@planeamiento.tal',
            'password' => 'TestOnly-Planeamiento-2026!'
        ]
    ]);
});

assertTest("Caso 1: Login de asistente retorna HTTP 200 y success: true", 
    $res1['code'] === 200 && ($res1['json']['success'] ?? false) === true);
assertTest("Caso 1: Rol de asistente es ASISTENTE y es_jefe es false", 
    $res1['json']['data']['user']['rol'] === 'ASISTENTE' && $res1['json']['data']['user']['es_jefe'] === false);
assertTest("Caso 1: Se emite token criptográfico de sesión no vacío", 
    !empty($res1['json']['data']['token']));

$tokenAsistente = $res1['json']['data']['token'];

// -----------------------------------------------------------------------------
// Caso 2: Login Exitoso de Jefe de Planeamiento
// -----------------------------------------------------------------------------
$res2 = runController(function () use ($auth) {
    $auth->login([
        'body' => [
            'email'    => 'jefe.planeamiento@planeamiento.tal',
            'password' => 'TestOnly-Planeamiento-2026!'
        ]
    ]);
});

assertTest("Caso 2: Login de jefe retorna HTTP 200 y rol JEFE_PLANEAMIENTO", 
    $res2['code'] === 200 && $res2['json']['data']['user']['rol'] === 'JEFE_PLANEAMIENTO');
assertTest("Caso 2: Indicador es_jefe es true para jefatura", 
    $res2['json']['data']['user']['es_jefe'] === true);

$tokenJefe = $res2['json']['data']['token'];

// -----------------------------------------------------------------------------
// Caso 3: Pruebas Negativas de Login
// -----------------------------------------------------------------------------
echo "\n--- Probando Pruebas Negativas de Autenticación (Seguridad) ---\n";

$res3 = runController(function () use ($auth) {
    $auth->login([
        'body' => [
            'email'    => 'mchumbes@planeamiento.tal',
            'password' => 'PasswordIncorrecta999'
        ]
    ]);
});

assertTest("Caso 3 (Negativo): Contraseña errónea retorna HTTP 401 y ERR_INVALID_CREDENTIALS", 
    $res3['code'] === 401 && ($res3['json']['error']['code'] ?? '') === 'ERR_INVALID_CREDENTIALS', $res3);

$res4 = runController(function () use ($auth) {
    $auth->login([
        'body' => [
            'email'    => 'noexiste@planeamiento.tal',
            'password' => 'CualquierPassword!'
        ]
    ]);
});

assertTest("Caso 4 (Negativo): Email no registrado retorna HTTP 401", 
    $res4['code'] === 401 && ($res4['json']['error']['code'] ?? '') === 'ERR_INVALID_CREDENTIALS');

$res5 = runController(function () use ($auth) {
    $auth->login([
        'body' => [
            'email'    => '',
            'password' => ''
        ]
    ]);
});

assertTest("Caso 5 (Negativo): Campos vacíos retornan HTTP 422 y ERR_VALIDATION_FAILED", 
    $res5['code'] === 422 && ($res5['json']['error']['code'] ?? '') === 'ERR_VALIDATION_FAILED');

$res6 = runController(function () use ($auth) {
    $auth->login([
        'body' => [
            'email'    => 'inactivo@planeamiento.tal',
            'password' => 'TestOnly-Planeamiento-2026!'
        ]
    ]);
});

assertTest("Caso 6 (Negativo): Usuario con activo=0 es bloqueado con HTTP 403 y ERR_USER_INACTIVE", 
    $res6['code'] === 403 && ($res6['json']['error']['code'] ?? '') === 'ERR_USER_INACTIVE');

// -----------------------------------------------------------------------------
// Caso 7: Verificación de Sesión con Middleware (/api/auth/me)
// -----------------------------------------------------------------------------
echo "\n--- Probando Middleware AuthMiddleware y Endpoint /api/auth/me ---\n";

$middleware = new AuthMiddleware();

// 7a. Con token válido de asistente
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenAsistente;
$requestContext = [];
$middleware->handle($requestContext);

assertTest("Caso 7a: AuthMiddleware inyecta usuario en la petición con ID 1", 
    isset($requestContext['user']) && $requestContext['user']['id'] === 1);

$res7 = runController(function () use ($auth, $requestContext) {
    $auth->me($requestContext);
});

assertTest("Caso 7b: GET /api/auth/me responde HTTP 200 con datos del asistente", 
    $res7['code'] === 200 && $res7['json']['data']['user']['email'] === 'mchumbes@planeamiento.tal');

// 7c. Sin token (Negativo)
unset($_SERVER['HTTP_AUTHORIZATION']);
$_COOKIE = [];

$res8 = runController(function () use ($middleware) {
    $req = [];
    $middleware->handle($req);
});

assertTest("Caso 8 (Negativo): Petición sin token es rechazada por AuthMiddleware con HTTP 401", 
    $res8['code'] === 401 && ($res8['json']['error']['code'] ?? '') === 'ERR_UNAUTHORIZED');

// 7d. Con token manipulado / alterado (Negativo)
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token_manipulado_invalido_123456789';

$res9 = runController(function () use ($middleware) {
    $req = [];
    $middleware->handle($req);
});

assertTest("Caso 9 (Negativo): Token alterado/inexistente es rechazado con HTTP 401 ERR_SESSION_EXPIRED", 
    $res9['code'] === 401 && ($res9['json']['error']['code'] ?? '') === 'ERR_SESSION_EXPIRED');

// -----------------------------------------------------------------------------
// Caso 10: Logout y Revocación de Sesión
// -----------------------------------------------------------------------------
echo "\n--- Probando Cierre de Sesión (Logout) e Invalidación de Token ---\n";

$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokenAsistente;
$requestLogout = [
    'user'  => ['id' => 1],
    'token' => $tokenAsistente
];

$res10 = runController(function () use ($auth, $requestLogout) {
    $auth->logout($requestLogout);
});

assertTest("Caso 10a: Logout exitoso retorna HTTP 200", 
    $res10['code'] === 200 && ($res10['json']['success'] ?? false) === true);

// Ahora intentamos usar el token revocado de nuevo
$res10b = runController(function () use ($middleware) {
    $req = [];
    $middleware->handle($req);
});

assertTest("Caso 10b: Token revocado en logout ya no permite autenticación (HTTP 401)", 
    $res10b['code'] === 401 && ($res10b['json']['error']['code'] ?? '') === 'ERR_SESSION_EXPIRED');

// -----------------------------------------------------------------------------
// Caso 11: Auditoría Inmutable de Eventos
// -----------------------------------------------------------------------------
echo "\n--- Probando Trazabilidad y Auditoría de Seguridad (RN-13) ---\n";

$stmtAudit = $pdo->query("SELECT accion, COUNT(*) as cant FROM auditoria_eventos GROUP BY accion");
$auditCounts = [];
while ($row = $stmtAudit->fetch()) {
    $auditCounts[$row['accion']] = (int)$row['cant'];
}

assertTest("Caso 11a: Auditoría registró LOGIN_SUCCESS", 
    ($auditCounts['LOGIN_SUCCESS'] ?? 0) >= 2);
assertTest("Caso 11b: Auditoría registró LOGIN_FAILED", 
    ($auditCounts['LOGIN_FAILED'] ?? 0) >= 2);
assertTest("Caso 11c: Auditoría registró LOGIN_BLOCKED_INACTIVE", 
    ($auditCounts['LOGIN_BLOCKED_INACTIVE'] ?? 0) >= 1);
assertTest("Caso 11d: Auditoría registró LOGOUT_SUCCESS", 
    ($auditCounts['LOGOUT_SUCCESS'] ?? 0) >= 1);

echo "\n=======================================================\n";
echo "RESULTADO FINAL: {$passed} de {$total} pruebas superadas exitosamente (100% PASS).\n";
echo "=======================================================\n";
exit(0);
