<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Config\Database;
use App\Controllers\RealtimeSyncController;
use App\Core\Response;
use App\Models\RealtimeEvent;

Response::$exitOnSend = false;

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('CREATE TABLE usuarios (
    id INTEGER PRIMARY KEY,
    codigo TEXT NOT NULL,
    nombre TEXT NOT NULL,
    email TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    rol TEXT NOT NULL,
    cargo TEXT NOT NULL,
    activo INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
)');
Database::setConnection($pdo);

$now = gmdate('Y-m-d H:i:s');
$users = [
    [1, 'ASIST-01', 'Asistente Uno', 'uno@tal.test', 'ASISTENTE'],
    [2, 'ASIST-02', 'Asistente Dos', 'dos@tal.test', 'ASISTENTE'],
    [3, 'JEFE-PER', 'Jefe Personal', 'personal@tal.test', 'JEFE_PERSONAL'],
    [4, 'JEFE-PLA', 'Jefe Área', 'area@tal.test', 'JEFE_PLANEAMIENTO'],
    [5, 'ASIST-03', 'Asistente Tres', 'tres@tal.test', 'ASISTENTE'],
];
$insert = $pdo->prepare('INSERT INTO usuarios (id, codigo, nombre, email, password_hash, rol, cargo, activo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)');
foreach ($users as [$id, $codigo, $nombre, $email, $rol]) {
    $insert->execute([$id, $codigo, $nombre, $email, 'hash', $rol, $rol, $now, $now]);
}

$total = 0;
$passed = 0;
function assertRealtime(bool $condition, string $name): void
{
    global $total, $passed;
    $total++;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$name}\n";
        return;
    }
    echo "  [FAIL] {$name}\n";
}

function captureRealtime(callable $callback): array
{
    Response::$hasSentResponse = false;
    ob_start();
    $callback();
    $raw = ob_get_clean();
    return json_decode($raw, true) ?? [];
}

echo "=== PRUEBAS: SINCRONIZACIÓN OPERATIVA EN TIEMPO REAL (RF-19, RN-16) ===\n\n";

$task = ['id' => 91, 'creador_id' => 2, 'responsable_id' => 1, 'revisor_id' => null];
$actor = ['id' => 3];
$eventId = RealtimeEvent::publishTask('TASK_COMMENT_CREATED', $task, $actor);

assertRealtime($eventId !== null && $eventId > 0, 'Se persiste un evento de tarea con destinatarios explícitos');
assertRealtime(count(RealtimeEvent::eventsForUser(1, 0)) === 1, 'El responsable recibe el evento de su tarea');
assertRealtime(count(RealtimeEvent::eventsForUser(2, 0)) === 1, 'El creador recibe el evento de su tarea');
assertRealtime(count(RealtimeEvent::eventsForUser(3, 0)) === 1 && count(RealtimeEvent::eventsForUser(4, 0)) === 1, 'Las jefaturas activas reciben el evento compartido');
assertRealtime(RealtimeEvent::eventsForUser(5, 0) === [], 'Un asistente ajeno no recibe ni puede inferir cambios de otra tarea');

$assistantEvents = RealtimeEvent::eventsForUser(1, 0);
$firstEvent = $assistantEvents[0] ?? [];
assertRealtime(!array_key_exists('payload', $firstEvent) && !array_key_exists('titulo', $firstEvent), 'El evento entregado no expone contenido ni datos sensibles');
assertRealtime(RealtimeEvent::eventsForUser(1, (int)$eventId) === [], 'El cursor evita entregar el mismo evento dos veces');

$systemEventId = RealtimeEvent::publishSystem('BOARD_COLUMN_UPDATED', 'COLUMNA', 8, $actor);
assertRealtime($systemEventId !== null && count(RealtimeEvent::eventsForUser(5, (int)$eventId)) === 1, 'Cambio de columna llega a todos los usuarios activos del tablero');

$controller = new RealtimeSyncController();
$cursorResponse = captureRealtime(fn() => $controller->cursor(['user' => ['id' => 1]]));
assertRealtime((int)($cursorResponse['data']['cursor'] ?? 0) === (int)$systemEventId, 'Endpoint de cursor devuelve el último evento autorizado del usuario');

$eventsResponse = captureRealtime(fn() => $controller->events(['user' => ['id' => 1], 'query' => ['after' => (string)$eventId, 'wait' => '0']]));
assertRealtime(count($eventsResponse['data']['eventos'] ?? []) === 1 && (int)($eventsResponse['data']['cursor'] ?? 0) === (int)$systemEventId, 'Endpoint autenticado entrega eventos posteriores y avanza cursor');

echo "\nResultado: {$passed}/{$total} pruebas aprobadas.\n";
exit($passed === $total ? 0 : 1);
