<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Config\Database;
use App\Controllers\DependencyController;
use App\Controllers\TaskController;
use App\Controllers\ApprovalController;
use App\Core\Middleware\AuthMiddleware;
use App\Core\Response;
use App\Core\Router;
use App\Models\Approval;
use App\Models\BoardColumn;
use App\Models\Session;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\User;

Response::$exitOnSend = false;

echo "=== INICIANDO SUITE DE PRUEBAS AUTOMATIZADAS: MVP-07 (DEPENDENCIAS SECUENCIALES/PARALELAS Y DAG) ===\n\n";

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
        color_hex TEXT NOT NULL DEFAULT '#64748b',
        activo INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL
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

    CREATE TABLE tarea_comentarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tarea_id INTEGER NOT NULL,
        usuario_id INTEGER NOT NULL,
        comentario TEXT NOT NULL,
        es_evidencia INTEGER NOT NULL DEFAULT 0,
        archivo_nombre_original TEXT,
        archivo_ruta_hash TEXT,
        archivo_mime TEXT,
        archivo_size_bytes INTEGER,
        created_at TEXT NOT NULL,
        FOREIGN KEY (tarea_id) REFERENCES tareas(id) ON DELETE CASCADE,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
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

    CREATE TABLE tarea_aprobaciones (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tarea_id INTEGER NOT NULL,
        revisor_id INTEGER NOT NULL,
        estado TEXT NOT NULL DEFAULT 'PENDIENTE',
        comentario_entrega TEXT,
        observaciones_devolucion TEXT,
        fecha_solicitud TEXT NOT NULL,
        fecha_resolucion TEXT,
        FOREIGN KEY (tarea_id) REFERENCES tareas(id) ON DELETE CASCADE,
        FOREIGN KEY (revisor_id) REFERENCES usuarios(id)
    );

    CREATE TABLE tarea_dependencias (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tarea_id INTEGER NOT NULL,
        predecesora_id INTEGER NOT NULL,
        tipo_dependencia TEXT NOT NULL DEFAULT 'AND_FINISH_TO_START',
        created_at TEXT NOT NULL,
        FOREIGN KEY (tarea_id) REFERENCES tareas(id) ON DELETE CASCADE,
        FOREIGN KEY (predecesora_id) REFERENCES tareas(id) ON DELETE CASCADE,
        UNIQUE(tarea_id, predecesora_id)
    );
");

// Inyectar conexión de prueba
Database::setConnection($pdo);

// -----------------------------------------------------------------------------
// 2. Insertar Datos de Prueba (Usuarios y Columnas)
// -----------------------------------------------------------------------------
$now = gmdate('Y-m-d H:i:s');
$passwordHash = password_hash('Planeamiento2026!', PASSWORD_BCRYPT);

// Columnas Kanban (1: Pendiente, 2: En proceso, 3: Bloqueada, 4: En revisión, 5: Finalizada)
$pdo->exec("
    INSERT INTO tableros_columnas (id, nombre, orden, tipo_estado, color_hex, activo, created_at) VALUES
    (1, 'Pendiente', 1, 'PENDIENTE', '#64748b', 1, '{$now}'),
    (2, 'En proceso', 2, 'EN_PROCESO', '#2563eb', 1, '{$now}'),
    (3, 'Bloqueada', 3, 'BLOQUEADA', '#e11d48', 1, '{$now}'),
    (4, 'En revisión', 4, 'EN_REVISION', '#d97706', 1, '{$now}'),
    (5, 'Finalizada', 5, 'FINALIZADA', '#059669', 1, '{$now}');
");

// Usuarios
$pdo->exec("
    INSERT INTO usuarios (id, codigo, nombre, email, password_hash, rol, cargo, activo, created_at, updated_at) VALUES
    (1, 'ASIST-01', 'Marco Chumbes', 'mchumbes@planeamiento.tal', '{$passwordHash}', 'ASISTENTE', 'Asistente 1', 1, '{$now}', '{$now}'),
    (2, 'ASIST-02', 'Asistente Dos', 'asistente2@planeamiento.tal', '{$passwordHash}', 'ASISTENTE', 'Asistente 2', 1, '{$now}', '{$now}'),
    (3, 'JEFE-01', 'Elena Vega', 'jefe.planeamiento@planeamiento.tal', '{$passwordHash}', 'JEFE_PLANEAMIENTO', 'Jefe Planeamiento', 1, '{$now}', '{$now}');
");

// Crear sesiones y tokens
$asist1Token = Session::create(1);
$asist2Token = Session::create(2);
$jefeToken   = Session::create(3);

$userAsist1 = User::findById(1);
$userAsist2 = User::findById(2);
$userJefe   = User::findById(3);

// -----------------------------------------------------------------------------
// Helper para Assertions
// -----------------------------------------------------------------------------
$testsCount = 0;
$testsPassed = 0;

function assertTest(bool $condition, string $testName, string $details = ''): void
{
    global $testsCount, $testsPassed;
    $testsCount++;
    if ($condition) {
        $testsPassed++;
        echo "  [PASS] Test #{$testsCount}: {$testName}\n";
    } else {
        echo "  [FAIL] Test #{$testsCount}: {$testName}\n";
        if ($details !== '') {
            echo "         Detalles: {$details}\n";
        }
    }
}

function captureResponse(callable $callback): array
{
    ob_start();
    $callback();
    $output = ob_get_clean();
    $data = json_decode($output, true);
    return [
        'status' => Response::$lastStatusCode,
        'json'   => $data,
        'raw'    => $output
    ];
}

// -----------------------------------------------------------------------------
// Batería de Pruebas: Dependencias y DAG
// -----------------------------------------------------------------------------

echo "--- 1. Pruebas Unitarias del Modelo TaskDependency ---\n";

// Crear 4 tareas para pruebas de grafo
$t1 = Task::create(['titulo' => 'Tarea 1 Base (Predecesora A)', 'tipo_tarea' => 'AREA'], $userJefe);
$t2 = Task::create(['titulo' => 'Tarea 2 Base (Predecesora B)', 'tipo_tarea' => 'AREA'], $userJefe);
$t3 = Task::create(['titulo' => 'Tarea 3 Sucesora (Dependiente)', 'tipo_tarea' => 'AREA'], $userJefe);
$t4 = Task::create(['titulo' => 'Tarea 4 Final', 'tipo_tarea' => 'AREA'], $userJefe);

// Test 1: Rechazo de auto-dependencia (t1 no puede depender de t1)
try {
    TaskDependency::add((int)$t1['id'], (int)$t1['id'], $userJefe);
    assertTest(false, "Rechazo de auto-dependencia");
} catch (InvalidArgumentException $e) {
    assertTest($e->getCode() === 422, "Rechazo de auto-dependencia con 422");
}

// Test 2: Agregar dependencia válida (t3 depende de t1)
$resDep = TaskDependency::add((int)$t3['id'], (int)$t1['id'], $userJefe);
assertTest(
    $resDep['tarea_id'] === (int)$t3['id'] && $resDep['predecesora_id'] === (int)$t1['id'],
    "Agregar dependencia válida entre t3 y t1"
);

// Test 3: Regla RN-08: t1 está en PENDIENTE (no finalizada) => t3 se bloquea automáticamente
$t3Updated = Task::findById((int)$t3['id']);
assertTest(
    ($t3Updated['columna_tipo_estado'] ?? '') === BoardColumn::ESTADO_BLOQUEADA,
    "Regla RN-08: Tarea t3 pasa automáticamente a estado BLOQUEADA al depender de t1 abierta"
);

// Test 4: Rechazo de dependencia duplicada
try {
    TaskDependency::add((int)$t3['id'], (int)$t1['id'], $userJefe);
    assertTest(false, "Rechazo de dependencia duplicada");
} catch (RuntimeException $e) {
    assertTest($e->getCode() === 409, "Rechazo de dependencia duplicada con 409 Conflict");
}

// Test 5: Regla RN-07: Detección de Ciclo Directo (Intentar hacer que t1 dependa de t3)
try {
    TaskDependency::add((int)$t1['id'], (int)$t3['id'], $userJefe);
    assertTest(false, "Detección de ciclo directo t1 -> t3");
} catch (RuntimeException $e) {
    assertTest(
        $e->getCode() === 409 && str_contains(strtolower($e->getMessage()), 'ciclo'),
        "Regla RN-07: Detección y bloqueo de ciclo directo t1 -> t3 con 409"
    );
}

// Test 6: Dependencia múltiple (t3 también depende de t2 - Ramas paralelas RF-15)
TaskDependency::add((int)$t3['id'], (int)$t2['id'], $userJefe);
$depsT3 = TaskDependency::getByTask((int)$t3['id']);
assertTest(
    count($depsT3['predecesoras']) === 2 && $depsT3['bloqueada_por_dependencias'] === true,
    "Soporte para múltiples predecesoras (t3 depende de t1 y t2 en paralelo)"
);

// Test 7: Detección de Ciclo Indirecto (t4 depende de t3, e intentar que t1 dependa de t4)
TaskDependency::add((int)$t4['id'], (int)$t3['id'], $userJefe); // Grafo: t1 -> t3 -> t4
try {
    TaskDependency::add((int)$t1['id'], (int)$t4['id'], $userJefe); // Ciclo: t4 -> t1
    assertTest(false, "Detección de ciclo indirecto en DAG");
} catch (RuntimeException $e) {
    assertTest(
        $e->getCode() === 409 && str_contains(strtolower($e->getMessage()), 'ciclo'),
        "Regla RN-07: Detección y bloqueo de ciclo indirecto (t1 -> t3 -> t4 -> t1)"
    );
}

// Test 8: Regla RN-08: Intento de mover tarea bloqueada (t3) a EN_PROCESO es rechazado
$colEnProceso = BoardColumn::findByTipoEstado('EN_PROCESO');
$transResult = Task::transition((int)$t3['id'], (int)$colEnProceso['id'], [], $userJefe);
assertTest(
    $transResult['success'] === false && $transResult['status'] === 409 && $transResult['code'] === 'ERR_TASK_BLOCKED_BY_DEPENDENCIES',
    "Regla RN-08: Intento de mover tarea bloqueada t3 a EN_PROCESO retorna 409 Conflict"
);

// Test 9: Regla AND (RN-08): Finalizar 1 de 2 predecesoras (t1 se finaliza, pero t2 sigue abierta)
// t1 se mueve a Finalizada con confirmacion_cierre: true
$colFinalizada = BoardColumn::findByTipoEstado('FINALIZADA');
$transT1 = Task::transition((int)$t1['id'], (int)$colFinalizada['id'], ['confirmacion_cierre' => true], $userJefe);
assertTest($transT1['success'] === true, "Predecesora t1 pasa a FINALIZADA");

$t3Mid = Task::findById((int)$t3['id']);
assertTest(
    ($t3Mid['columna_tipo_estado'] ?? '') === BoardColumn::ESTADO_BLOQUEADA,
    "Regla AND (RN-08): t3 se mantiene BLOQUEADA tras finalizar 1/2 predecesoras"
);

// Test 10: Regla AND & RF-16: Finalizar la segunda predecesora (t2 se finaliza) => t3 se desbloquea automáticamente a PENDIENTE
$transT2 = Task::transition((int)$t2['id'], (int)$colFinalizada['id'], ['confirmacion_cierre' => true], $userJefe);
assertTest($transT2['success'] === true, "Predecesora t2 pasa a FINALIZADA");

$t3Unlocked = Task::findById((int)$t3['id']);
assertTest(
    ($t3Unlocked['columna_tipo_estado'] ?? '') === BoardColumn::ESTADO_PENDIENTE,
    "Regla RF-16 & RN-08: Desbloqueo automático en cascada: t3 pasa a PENDIENTE cuando TODAS (2/2) finalizan"
);

// Test 11: Desbloqueo tras eliminar dependencia bloqueante
$t5 = Task::create(['titulo' => 'Tarea 5', 'tipo_tarea' => 'AREA'], $userJefe);
$t6 = Task::create(['titulo' => 'Tarea 6', 'tipo_tarea' => 'AREA'], $userJefe);
TaskDependency::add((int)$t6['id'], (int)$t5['id'], $userJefe); // t6 se bloquea por t5
$t6Before = Task::findById((int)$t6['id']);
assertTest(($t6Before['columna_tipo_estado'] ?? '') === BoardColumn::ESTADO_BLOQUEADA, "t6 queda bloqueada por t5");

TaskDependency::remove((int)$t6['id'], (int)$t5['id'], $userJefe);
$t6After = Task::findById((int)$t6['id']);
assertTest(
    ($t6After['columna_tipo_estado'] ?? '') === BoardColumn::ESTADO_PENDIENTE,
    "Desbloqueo automático de t6 tras eliminar la relación de dependencia con t5"
);

// Test 12: Desbloqueo en cascada tras Aprobación formal de jefatura (RN-10)
$tAppr = Task::create([
    'titulo'              => 'Tarea Requerida con Aprobación',
    'tipo_tarea'          => 'AREA',
    'requiere_aprobacion' => 1
], $userJefe);

$tDepAppr = Task::create(['titulo' => 'Tarea Sucesora de Aprobación', 'tipo_tarea' => 'AREA'], $userJefe);
TaskDependency::add((int)$tDepAppr['id'], (int)$tAppr['id'], $userJefe);

// Enviar tAppr a aprobación y resolver
Approval::solicitar((int)$tAppr['id'], (int)$userJefe['id'], 'Listo para revisión formal');
Approval::resolver((int)$tAppr['id'], (int)$userJefe['id'], 'APROBAR');

$tDepApprAfter = Task::findById((int)$tDepAppr['id']);
assertTest(
    ($tDepApprAfter['columna_tipo_estado'] ?? '') === BoardColumn::ESTADO_PENDIENTE,
    "Desbloqueo en cascada ejecutado al emitirse la aprobación formal favorable (RN-10 + RF-16)"
);

// -----------------------------------------------------------------------------
// Pruebas de Integración HTTP y Router
// -----------------------------------------------------------------------------
echo "\n--- 2. Pruebas de Integración HTTP (Router y DependencyController) ---\n";

$router = new Router();
$authMw = new AuthMiddleware();

$router->get('/api/tareas/{id}/dependencias', [DependencyController::class, 'index'], [$authMw]);
$router->post('/api/tareas/{id}/dependencias', [DependencyController::class, 'store'], [$authMw]);
$router->delete('/api/tareas/{id}/dependencias/{pred_id}', [DependencyController::class, 'destroy'], [$authMw]);

// Test 13: Autenticación requerida para GET dependencias (401)
$_SERVER['REQUEST_METHOD'] = 'GET';
unset($_SERVER['HTTP_AUTHORIZATION']);
$res = captureResponse(fn() => $router->dispatch('GET', '/api/tareas/' . $t3['id'] . '/dependencias'));
assertTest($res['status'] === 401 && $res['json']['success'] === false, "GET /api/tareas/{id}/dependencias exige autenticación (401)");

// Test 14: GET dependencias con autenticación (200 OK)
$_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$jefeToken}";
$res = captureResponse(fn() => $router->dispatch('GET', '/api/tareas/' . $t3['id'] . '/dependencias'));
assertTest(
    $res['status'] === 200 && ($res['json']['success'] ?? false) === true && isset($res['json']['data']['predecesoras']),
    "GET /api/tareas/{id}/dependencias retorna 200 OK con estructura DAG",
    json_encode($res)
);

// Test 15: POST dependencia con falta de body o parámetro inválido (422)
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$jefeToken}";
$_POST = [];
$res = captureResponse(function() use ($router, $t4) {
    $router->dispatch('POST', '/api/tareas/' . $t4['id'] . '/dependencias');
});
assertTest(
    $res['status'] === 422 && $res['json']['error']['code'] === 'ERR_VALIDATION_FAILED',
    "POST dependencias sin 'predecesora_id' retorna 422 Unprocessable Entity"
);

// Test 16: POST crear dependencia válida vía API (201 Created)
$tHttpA = Task::create(['titulo' => 'HTTP Base A', 'tipo_tarea' => 'AREA'], $userJefe);
$tHttpB = Task::create(['titulo' => 'HTTP Sucesora B', 'tipo_tarea' => 'AREA'], $userJefe);

$_POST = ['predecesora_id' => (int)$tHttpA['id']];
$res = captureResponse(function() use ($router, $tHttpB) {
    $router->dispatch('POST', '/api/tareas/' . $tHttpB['id'] . '/dependencias');
});
assertTest(
    $res['status'] === 201 && $res['json']['success'] === true && $res['json']['data']['predecesora_id'] === (int)$tHttpA['id'],
    "POST dependencias registra relación con 201 Created"
);

// Test 17: POST ciclo detectado vía API responde 409 Conflict
$_POST = ['predecesora_id' => (int)$tHttpB['id']];
$res = captureResponse(function() use ($router, $tHttpA) {
    $router->dispatch('POST', '/api/tareas/' . $tHttpA['id'] . '/dependencias');
});
assertTest(
    $res['status'] === 409 && $res['json']['error']['code'] === 'ERR_CYCLIC_DEPENDENCY',
    "POST dependencias rechaza ciclo en el grafo con 409 ERR_CYCLIC_DEPENDENCY"
);

// Test 18: DELETE dependencia vía API (200 OK)
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'DELETE';
$res = captureResponse(function() use ($router, $tHttpB, $tHttpA) {
    $router->dispatch('DELETE', '/api/tareas/' . $tHttpB['id'] . '/dependencias/' . $tHttpA['id']);
});
assertTest(
    $res['status'] === 200 && $res['json']['success'] === true,
    "DELETE dependencias elimina relación y responde 200 OK"
);

// Test 19: DELETE dependencia ya inexistente responde 404 Not Found
$res = captureResponse(function() use ($router, $tHttpB, $tHttpA) {
    $router->dispatch('DELETE', '/api/tareas/' . $tHttpB['id'] . '/dependencias/' . $tHttpA['id']);
});
assertTest(
    $res['status'] === 404 && $res['json']['error']['code'] === 'ERR_DEPENDENCY_NOT_FOUND',
    "DELETE dependencia inexistente responde 404 ERR_DEPENDENCY_NOT_FOUND"
);

// Test 20: Permisos RBAC: Asistente no puede agregar dependencia en tarea ajena (403 Forbidden)
$tAjena = Task::create(['titulo' => 'Tarea de Asistente 2', 'tipo_tarea' => 'PERSONAL'], $userAsist2);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$asist1Token}";
$_POST = ['predecesora_id' => (int)$tHttpA['id']];
$res = captureResponse(function() use ($router, $tAjena) {
    $router->dispatch('POST', '/api/tareas/' . $tAjena['id'] . '/dependencias');
});
assertTest(
    $res['status'] === 403,
    "Asistente 1 recibe 403 Forbidden al intentar alterar dependencias de tarea privada ajena"
);

// Test 21: Permisos RBAC: Asistente 1 no puede ver dependencias de tarea privada ajena (403)
$res = captureResponse(function() use ($router, $tAjena) {
    $router->dispatch('GET', '/api/tareas/' . $tAjena['id'] . '/dependencias');
});
assertTest(
    $res['status'] === 403,
    "Asistente 1 recibe 403 Forbidden al consultar dependencias de tarea privada ajena (anti-IDOR)"
);

// Test 22: Tarea inexistente responde 404 Not Found
$_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$jefeToken}";
$res = captureResponse(function() use ($router) {
    $router->dispatch('GET', '/api/tareas/99999/dependencias');
});
assertTest(
    $res['status'] === 404 && $res['json']['error']['code'] === 'ERR_TASK_NOT_FOUND',
    "GET dependencias de tarea inexistente responde 404 ERR_TASK_NOT_FOUND"
);

// Test 23: Auditoría inmutable registra creación y eliminación de dependencias
$auditCount = (int)$pdo->query("SELECT COUNT(*) FROM auditoria_eventos WHERE entidad_tipo = 'DEPENDENCIA'")->fetchColumn();
assertTest(
    $auditCount > 0,
    "Registro inmutable en auditoria_eventos verificado para operaciones de dependencias"
);

// -----------------------------------------------------------------------------
// Resumen Final
// -----------------------------------------------------------------------------
echo "\n=======================================================\n";
echo "RESULTADOS FINALES DE MVP-07:\n";
echo "Pruebas Ejecutadas: {$testsCount}\n";
echo "Pruebas Superadas:  {$testsPassed}\n";
echo "Porcentaje Éxito:   " . round(($testsPassed / $testsCount) * 100, 2) . "%\n";
echo "=======================================================\n";

if ($testsPassed === $testsCount) {
    echo "\n>>> ¡TODAS LAS PRUEBAS DE MVP-07 HAN SIDO SUPERADAS EXITOSAMENTE (100% PASS)! <<<\n";
    exit(0);
} else {
    echo "\n>>> ALGUNAS PRUEBAS HAN FALLADO. REVISAR DETALLES ARRIBA. <<<\n";
    exit(1);
}
