<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Config\Database;
use App\Controllers\UserManagementController;
use App\Core\Response;
use App\Models\Session;
use App\Models\User;

Response::$exitOnSend = false;

echo "=== PRUEBAS: GESTIÓN DE USUARIOS Y JERARQUÍA (RF-14, RN-12) ===\n\n";

$pdo = new \PDO('sqlite::memory:', null, null, [
    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
]);
$pdo->exec("CREATE TABLE usuarios (
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
    created_at TEXT NOT NULL
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
);");
Database::setConnection($pdo);

$password = 'TestOnly-Planeamiento-2026!';
$hash = password_hash($password, PASSWORD_BCRYPT);
$now = gmdate('Y-m-d H:i:s');
$seed = [
    [1, 'ASIST-01', 'Asistente Uno', 'asistente@planeamiento.tal', $hash, User::ROL_ASISTENTE, 'Asistente', 1],
    [2, 'JEFE-PER', 'Jefe de Personal', 'personal@planeamiento.tal', $hash, User::ROL_JEFE_PERSONAL, 'Jefe de Personal', 1],
    [3, 'JEFE-AREA', 'Jefe de Área', 'area@planeamiento.tal', $hash, User::ROL_JEFE_PLANEAMIENTO, 'Jefe de Área', 1],
    [4, 'ASIST-INA', 'Asistente Inactivo', 'inactivo@planeamiento.tal', $hash, User::ROL_ASISTENTE, 'Asistente', 0]
];
$insert = $pdo->prepare('INSERT INTO usuarios (id, codigo, nombre, email, password_hash, rol, cargo, activo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
foreach ($seed as $user) {
    $insert->execute([...$user, $now, $now]);
}

$total = 0;
$passed = 0;
function assertManagement(string $name, bool $condition, string $detail = ''): void
{
    global $total, $passed;
    $total++;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$name}\n";
        return;
    }
    echo "  [FAIL] {$name}" . ($detail !== '' ? " - {$detail}" : '') . "\n";
}

function captureManagement(callable $callback): array
{
    ob_start();
    $callback();
    $raw = ob_get_clean();
    return ['json' => json_decode($raw, true) ?? [], 'status' => Response::$lastStatusCode, 'raw' => $raw];
}

$controller = new UserManagementController();
$assistant = User::formatPublic(User::findById(1));
$chiefPersonal = User::formatPublic(User::findById(2));
$chiefArea = User::formatPublic(User::findById(3));

$result = captureManagement(fn() => $controller->index(['user' => $assistant, 'query' => []]));
assertManagement('Un asistente no accede al módulo administrativo', $result['status'] === 403 && ($result['json']['error']['code'] ?? '') === 'ERR_FORBIDDEN_ROLE');

$result = captureManagement(fn() => $controller->index(['user' => $chiefPersonal, 'query' => ['incluir_inactivos' => '1']]));
assertManagement('Jefatura lista cuentas activas e inactivas', $result['status'] === 200 && ($result['json']['data']['total'] ?? 0) === 4);

$areaSession = Session::create(3, '127.0.0.1', 'test');
$result = captureManagement(fn() => $controller->changeStatus([
    'user' => $chiefPersonal,
    'params' => ['id' => '3'],
    'body' => ['activo' => false]
]));
assertManagement('Jefe de Personal no puede desactivar a Jefe de Área', $result['status'] === 403 && ($result['json']['error']['code'] ?? '') === 'ERR_HIERARCHY_PROTECTED');
assertManagement('La desactivación bloqueada no invalida la sesión del Jefe de Área', Session::findByToken($areaSession) !== null);

$result = captureManagement(fn() => $controller->update([
    'user' => $chiefPersonal,
    'params' => ['id' => '3'],
    'body' => ['nombre' => 'Jefe de Área', 'email' => 'area@planeamiento.tal', 'rol' => User::ROL_JEFE_PLANEAMIENTO, 'cargo' => 'Jefe de Área Actualizado']
]));
assertManagement('Jefe de Personal puede editar una cuenta de Jefe de Área', $result['status'] === 200 && (User::findById(3)['cargo'] ?? '') === 'Jefe de Área Actualizado');

$assistantSession = Session::create(1, '127.0.0.1', 'test');
$result = captureManagement(fn() => $controller->changeStatus([
    'user' => $chiefPersonal,
    'params' => ['id' => '1'],
    'body' => ['activo' => false]
]));
assertManagement('Jefe de Personal puede desactivar a un asistente', $result['status'] === 200 && (int)(User::findById(1)['activo'] ?? 1) === 0);
assertManagement('Desactivar una cuenta revoca sus sesiones', Session::findByToken($assistantSession) === null);

$result = captureManagement(fn() => $controller->changeStatus([
    'user' => $chiefArea,
    'params' => ['id' => '1'],
    'body' => ['activo' => true]
]));
assertManagement('Jefe de Área puede reactivar una cuenta', $result['status'] === 200 && (int)(User::findById(1)['activo'] ?? 0) === 1);

$roleSession = Session::create(1, '127.0.0.1', 'test');
$result = captureManagement(fn() => $controller->update([
    'user' => $chiefPersonal,
    'params' => ['id' => '1'],
    'body' => ['nombre' => 'Asistente Uno', 'email' => 'asistente@planeamiento.tal', 'rol' => User::ROL_JEFE_PERSONAL, 'cargo' => 'Jefatura de Personal']
]));
assertManagement('Jefatura puede asignar permisos por rol', $result['status'] === 200 && (User::findById(1)['rol'] ?? '') === User::ROL_JEFE_PERSONAL);
assertManagement('Cambiar rol revoca sesiones previas', Session::findByToken($roleSession) === null && ($result['json']['data']['requiere_reinicio'] ?? false) === true);

$result = captureManagement(fn() => $controller->create([
    'user' => $chiefArea,
    'body' => ['nombre' => 'Nueva Cuenta', 'email' => 'nueva@planeamiento.tal', 'rol' => User::ROL_ASISTENTE, 'cargo' => 'Asistente', 'password' => 'ClaveSegura99!']
]));
$created = $result['json']['data']['usuario'] ?? [];
assertManagement('Jefatura crea una cuenta con contraseña manual', $result['status'] === 201 && ($created['email'] ?? '') === 'nueva@planeamiento.tal');
assertManagement('La respuesta de creación no expone hashes ni contraseñas', !array_key_exists('password_hash', $created) && !str_contains($result['raw'], 'ClaveSegura99!'));

$newId = (int)($created['id'] ?? 0);
$passwordSession = Session::create($newId, '127.0.0.1', 'test');
$result = captureManagement(fn() => $controller->changePassword([
    'user' => $chiefPersonal,
    'params' => ['id' => (string)$newId],
    'body' => ['password' => 'OtraClave99!']
]));
$newUser = User::findById($newId);
assertManagement('Cambiar contraseña revoca sesiones y actualiza el hash', $result['status'] === 200 && Session::findByToken($passwordSession) === null && User::verifyPassword('OtraClave99!', $newUser['password_hash']));

$auditPassword = (string)$pdo->query("SELECT COALESCE(detalles_json, '') FROM auditoria_eventos WHERE accion = 'PASSWORD_CAMBIADA' ORDER BY id DESC LIMIT 1")->fetchColumn();
assertManagement('Auditoría de contraseña no registra secretos', !str_contains($auditPassword, 'OtraClave99!') && !str_contains($auditPassword, 'ClaveSegura99!'));

$result = captureManagement(fn() => $controller->changeStatus([
    'user' => $chiefArea,
    'params' => ['id' => '3'],
    'body' => ['activo' => 'invalido']
]));
assertManagement('El estado inválido se rechaza sin cambios', $result['status'] === 422 && (int)(User::findById(3)['activo'] ?? 0) === 1);

echo "\nResultado: {$passed}/{$total} pruebas aprobadas.\n";
exit($passed === $total ? 0 : 1);
