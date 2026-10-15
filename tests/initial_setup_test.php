<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Config\Database;
use App\Services\InitialSetupService;

$passed = 0;
$total = 0;
function check(bool $condition, string $message): void {
    global $passed, $total;
    $total++;
    if ($condition) { $passed++; echo "[PASS] {$message}\n"; return; }
    echo "[FAIL] {$message}\n";
    exit(1);
}

$pdo = new \PDO('sqlite::memory:');
$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
Database::setConnection($pdo);
Database::seedSqliteSchema($pdo);

check((int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() === 0, 'Una base nueva no crea usuarios demo');
check((int) $pdo->query('SELECT COUNT(*) FROM tareas')->fetchColumn() === 0, 'Una base nueva no crea tareas demo');
check((int) $pdo->query('SELECT COUNT(*) FROM tableros_columnas')->fetchColumn() === 5, 'Una base nueva conserva cinco columnas operativas');

try {
    InitialSetupService::createFirstAdmin(['nombre' => 'Admin', 'email' => 'admin@tal.test', 'password' => 'corta']);
    check(false, 'La contraseña inicial corta debe rechazarse');
} catch (\RuntimeException $exception) {
    check($exception->getCode() === 422, 'La contraseña inicial corta se rechaza');
}

$admin = InitialSetupService::createFirstAdmin([
    'nombre' => 'Administrador Inicial',
    'email' => 'admin@tal.test',
    'cargo' => 'Jefe de Área',
    'password' => 'ClaveInicial#2026'
]);
check($admin['rol'] === 'JEFE_PLANEAMIENTO', 'El instalador crea un Jefe de Área');
check((int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() === 1, 'Solo se crea una cuenta inicial');

try {
    InitialSetupService::createFirstAdmin([
        'nombre' => 'Segundo Admin', 'email' => 'segundo@tal.test', 'password' => 'OtraClave#2026'
    ]);
    check(false, 'El instalador no debe admitir una segunda cuenta inicial');
} catch (\RuntimeException $exception) {
    check($exception->getCode() === 409, 'El instalador queda bloqueado tras crear el primer administrador');
}

echo "Resultado: {$passed}/{$total} pruebas aprobadas.\n";
