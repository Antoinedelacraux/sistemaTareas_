<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Config\Database;
use App\Controllers\ApprovalController;
use App\Controllers\BoardController;
use App\Controllers\TaskController;
use App\Core\Middleware\AuthMiddleware;
use App\Core\Response;
use App\Core\Router;
use App\Models\Approval;
use App\Models\BoardColumn;
use App\Models\Session;
use App\Models\Task;
use App\Models\User;

Response::$exitOnSend = false;

echo "=== INICIANDO SUITE DE PRUEBAS AUTOMATIZADAS: MVP-06 (CONFIRMACIÓN DE CIERRE Y APROBACIONES) ===\n\n";

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
// 2. Semillas Iniciales
// -----------------------------------------------------------------------------
$pdo->exec("
    INSERT INTO tableros_columnas (id, nombre, orden, tipo_estado, color_hex, activo) VALUES
    (1, 'Pendiente', 1, 'PENDIENTE', '#64748b', 1),
    (2, 'En proceso', 2, 'EN_PROCESO', '#2563eb', 1),
    (3, 'Bloqueada', 3, 'BLOQUEADA', '#e11d48', 1),
    (4, 'En revisión', 4, 'EN_REVISION', '#d97706', 1),
    (5, 'Finalizada', 5, 'FINALIZADA', '#059669', 1);
");

$hash = password_hash('TestPass123!', PASSWORD_BCRYPT);
$now = gmdate('Y-m-d H:i:s');

$pdo->exec("
    INSERT INTO usuarios (id, codigo, nombre, email, password_hash, rol, cargo, activo, created_at, updated_at) VALUES
    (1, 'ASIST-01', 'Carlos Asistente', 'carlos@tal.com.pe', '{$hash}', 'ASISTENTE', 'Asistente 1', 1, '{$now}', '{$now}'),
    (2, 'ASIST-02', 'Lucia Asistente', 'lucia@tal.com.pe', '{$hash}', 'ASISTENTE', 'Asistente 2', 1, '{$now}', '{$now}'),
    (3, 'ASIST-03', 'Pedro Asistente', 'pedro@tal.com.pe', '{$hash}', 'ASISTENTE', 'Asistente 3', 1, '{$now}', '{$now}'),
    (4, 'JEFE-PER', 'Roberto Personal', 'roberto@tal.com.pe', '{$hash}', 'JEFE_PERSONAL', 'Jefe de Personal', 1, '{$now}', '{$now}'),
    (5, 'JEFE-PLA', 'Elena Planeamiento', 'elena@tal.com.pe', '{$hash}', 'JEFE_PLANEAMIENTO', 'Jefe de Planeamiento', 1, '{$now}', '{$now}');
");

$tokenAsist1 = Session::create(1, '127.0.0.1', 'PHPUnit');
$tokenAsist2 = Session::create(2, '127.0.0.1', 'PHPUnit');
$tokenJefe   = Session::create(5, '127.0.0.1', 'PHPUnit');

$userAsist1 = User::findById(1);
$userAsist2 = User::findById(2);
$userJefe   = User::findById(5);

$testsCount = 0;
$testsPassed = 0;

function runTest(string $nombre, callable $callback) {
    global $testsCount, $testsPassed;
    $testsCount++;
    try {
        ob_start();
        $callback();
        $output = ob_get_clean();
        $testsPassed++;
        echo "  [PASS] Test {$testsCount}: {$nombre}\n";
    } catch (\Throwable $e) {
        if (ob_get_level() > 0) ob_end_clean();
        echo "  [FAIL] Test {$testsCount}: {$nombre} -> " . $e->getMessage() . "\n";
    }
}

function captureResponse(callable $callback): array {
    ob_start();
    $callback();
    $raw = ob_get_clean();
    $json = json_decode($raw, true);
    return is_array($json) ? $json : ['raw' => $raw, 'lastStatusCode' => Response::$lastStatusCode];
}

// -----------------------------------------------------------------------------
// Tests de Aprobaciones y Circuito Formal (MVP-06)
// -----------------------------------------------------------------------------

// T01: GET /api/aprobaciones/pendientes sin sesión retorna 401
runTest("T01: GET /api/aprobaciones/pendientes sin sesión retorna 401 Unauthorized", function () {
    $ctrl = new ApprovalController();
    $res = captureResponse(fn() => $ctrl->pendientes(['user' => null]));
    if (($res['error']['code'] ?? '') !== 'ERR_UNAUTHORIZED') {
        throw new Exception("Se esperaba ERR_UNAUTHORIZED, se obtuvo: " . json_encode($res));
    }
});

// T02: POST /api/tareas/1/solicitar-aprobacion sin sesión retorna 401
runTest("T02: POST /api/tareas/1/solicitar-aprobacion sin sesión retorna 401 Unauthorized", function () {
    $ctrl = new ApprovalController();
    $res = captureResponse(fn() => $ctrl->solicitar(['user' => null, 'params' => ['id' => 1]]));
    if (($res['error']['code'] ?? '') !== 'ERR_UNAUTHORIZED') {
        throw new Exception("Se esperaba ERR_UNAUTHORIZED");
    }
});

// T03: POST /api/tareas/1/resolver-aprobacion sin sesión retorna 401
runTest("T03: POST /api/tareas/1/resolver-aprobacion sin sesión retorna 401 Unauthorized", function () {
    $ctrl = new ApprovalController();
    $res = captureResponse(fn() => $ctrl->resolver(['user' => null, 'params' => ['id' => 1]]));
    if (($res['error']['code'] ?? '') !== 'ERR_UNAUTHORIZED') {
        throw new Exception("Se esperaba ERR_UNAUTHORIZED");
    }
});

// T04: Asistente 1 crea tarea con requiere_aprobacion = 1
$tarea1Id = 0;
runTest("T04: Asistente 1 crea tarea oficial con requiere_aprobacion = 1 -> 201 Created", function () use ($userAsist1, &$tarea1Id) {
    $taskCtrl = new TaskController();
    $res = captureResponse(fn() => $taskCtrl->create([
        'user' => $userAsist1,
        'body' => [
            'titulo'              => 'Cierre de Balances de Campaña',
            'descripcion'         => 'Conciliar inventarios y mermas de empaque.',
            'tipo_tarea'          => 'AREA',
            'prioridad'           => 'ALTA',
            'requiere_aprobacion' => 1
        ]
    ]));

    if (!($res['success'] ?? false) || empty($res['data']['tarea']['id'])) {
        throw new Exception("No se creó la tarea: " . json_encode($res));
    }

    $tarea1Id = (int)$res['data']['tarea']['id'];
    if (!$res['data']['tarea']['requiere_aprobacion']) {
        throw new Exception("requiere_aprobacion debería ser true");
    }
});

// T05: [Seguridad RN-10] Intento de pasar directamente a Finalizada es rechazado con 409 Conflict
runTest("T05: [Seguridad RN-10] Cierre directo a Finalizada en tarea que requiere aprobación es rechazado (409 Conflict)", function () use ($userAsist1, $tarea1Id) {
    $boardCtrl = new BoardController();
    $res = captureResponse(fn() => $boardCtrl->transition([
        'user'   => $userAsist1,
        'params' => ['id' => $tarea1Id],
        'body'   => [
            'nueva_columna_id'    => 5, // Finalizada
            'confirmacion_cierre' => true
        ]
    ]));

    if (($res['error']['code'] ?? '') !== 'ERR_APPROVAL_REQUIRED') {
        throw new Exception("Se esperaba ERR_APPROVAL_REQUIRED, se obtuvo: " . json_encode($res));
    }
});

// T06: Asistente 1 solicita aprobación -> Tarea pasa a 'En revisión' (columna 4) y aprobación queda 'PENDIENTE'
runTest("T06: Asistente 1 solicita aprobación -> 200 OK, columna pasa a 'En revisión' y estado PENDIENTE", function () use ($userAsist1, $tarea1Id) {
    $approvalCtrl = new ApprovalController();
    $res = captureResponse(fn() => $approvalCtrl->solicitar([
        'user'   => $userAsist1,
        'params' => ['id' => $tarea1Id],
        'body'   => [
            'comentario_entrega' => 'Se adjunta reporte consolidado listo para revisión final de jefatura.'
        ]
    ]));

    if (!($res['success'] ?? false) || ($res['data']['estado'] ?? '') !== 'PENDIENTE') {
        throw new Exception("No se registró la solicitud: " . json_encode($res));
    }

    // Verificar que la tarea en BD ahora esté en columna 4 (En revisión)
    $t = Task::findById($tarea1Id);
    if ((int)$t['columna_id'] !== 4) {
        throw new Exception("La tarea debería estar en columna 4 (En revisión), está en: " . $t['columna_id']);
    }
});

// T07: [Seguridad Anti-IDOR] Asistente 2 intenta solicitar aprobación en tarea ajena -> 403 Forbidden
runTest("T07: [Seguridad Anti-IDOR] Asistente 2 intenta solicitar aprobación en tarea ajena -> 403 Forbidden", function () use ($userAsist2, $tarea1Id) {
    $approvalCtrl = new ApprovalController();
    $res = captureResponse(fn() => $approvalCtrl->solicitar([
        'user'   => $userAsist2,
        'params' => ['id' => $tarea1Id],
        'body'   => ['comentario_entrega' => 'Intento intrusivo']
    ]));

    if (($res['error']['code'] ?? '') !== 'ERR_FORBIDDEN_ACTION') {
        throw new Exception("Se esperaba ERR_FORBIDDEN_ACTION, se obtuvo: " . json_encode($res));
    }
});

// T08: [Supervisión Jefatura] Jefe consulta GET /api/aprobaciones/pendientes -> 200 OK y ve la solicitud
runTest("T08: [Supervisión Jefatura] Jefe consulta bandeja de aprobaciones pendientes -> 200 OK con 1 elemento", function () use ($userJefe, $tarea1Id) {
    $approvalCtrl = new ApprovalController();
    $res = captureResponse(fn() => $approvalCtrl->pendientes(['user' => $userJefe]));

    if (!($res['success'] ?? false) || count($res['data']) !== 1) {
        throw new Exception("Se esperaba 1 aprobación pendiente, se obtuvo: " . json_encode($res));
    }

    $item = $res['data'][0];
    if ((int)$item['tarea_id'] !== $tarea1Id || $item['responsable_nombre'] !== 'Carlos Asistente') {
        throw new Exception("Datos de tarea o responsable incorrectos: " . json_encode($item));
    }
});

// T09: [Seguridad RBAC] Asistente intenta consultar bandeja de aprobaciones pendientes -> 403 Forbidden
runTest("T09: [Seguridad RBAC] Asistente intenta consultar bandeja de aprobaciones pendientes -> 403 Forbidden", function () use ($userAsist1) {
    $approvalCtrl = new ApprovalController();
    $res = captureResponse(fn() => $approvalCtrl->pendientes(['user' => $userAsist1]));

    if (($res['error']['code'] ?? '') !== 'ERR_FORBIDDEN_ACTION') {
        throw new Exception("Se esperaba ERR_FORBIDDEN_ACTION, se obtuvo: " . json_encode($res));
    }
});

// T10: [Seguridad RBAC] Asistente intenta resolver la aprobación -> 403 Forbidden (solo jefes)
runTest("T10: [Seguridad RBAC] Asistente intenta resolver la aprobación -> 403 Forbidden", function () use ($userAsist1, $tarea1Id) {
    $approvalCtrl = new ApprovalController();
    $res = captureResponse(fn() => $approvalCtrl->resolver([
        'user'   => $userAsist1,
        'params' => ['id' => $tarea1Id],
        'body'   => ['accion' => 'APROBAR']
    ]));

    if (($res['error']['code'] ?? '') !== 'ERR_FORBIDDEN_APPROVAL_ACTION') {
        throw new Exception("Se esperaba ERR_FORBIDDEN_APPROVAL_ACTION, se obtuvo: " . json_encode($res));
    }
});

// T11: Jefe intenta resolver con acción no reconocida -> 422 Unprocessable Entity
runTest("T11: Jefe intenta resolver con acción inválida -> 422 Unprocessable Entity", function () use ($userJefe, $tarea1Id) {
    $approvalCtrl = new ApprovalController();
    $res = captureResponse(fn() => $approvalCtrl->resolver([
        'user'   => $userJefe,
        'params' => ['id' => $tarea1Id],
        'body'   => ['accion' => 'CANCELAR_INVENTADA']
    ]));

    if (($res['error']['code'] ?? '') !== 'ERR_VALIDATION_FAILED') {
        throw new Exception("Se esperaba ERR_VALIDATION_FAILED, se obtuvo: " . json_encode($res));
    }
});

// T12: [Regla RN-12] Jefe intenta DEVOLVER con observaciones vacías -> Rechazado 422 Unprocessable Entity
runTest("T12: [Regla RN-12] Jefe intenta devolver con observaciones vacías -> Rechazado 422 Unprocessable Entity", function () use ($userJefe, $tarea1Id) {
    $approvalCtrl = new ApprovalController();
    $res = captureResponse(fn() => $approvalCtrl->resolver([
        'user'   => $userJefe,
        'params' => ['id' => $tarea1Id],
        'body'   => [
            'accion'        => 'DEVOLVER',
            'observaciones' => '   ' // Espacios en blanco
        ]
    ]));

    if (($res['error']['code'] ?? '') !== 'ERR_VALIDATION_FAILED') {
        throw new Exception("Se esperaba ERR_VALIDATION_FAILED por observaciones vacías, se obtuvo: " . json_encode($res));
    }
});

// T13: [Regla RN-12] Jefe resuelve con DEVOLVER y observaciones válidas -> 200 OK
runTest("T13: [Regla RN-12] Jefe devuelve tarea con observaciones válidas -> 200 OK", function () use ($userJefe, $tarea1Id) {
    $approvalCtrl = new ApprovalController();
    $res = captureResponse(fn() => $approvalCtrl->resolver([
        'user'   => $userJefe,
        'params' => ['id' => $tarea1Id],
        'body'   => [
            'accion'        => 'DEVOLVER',
            'observaciones' => 'Se detectaron diferencias en el saldo del lote 3. Reconciliar y reenviar.'
        ]
    ]));

    if (!($res['success'] ?? false) || ($res['data']['estado'] ?? '') !== 'DEVUELTA') {
        throw new Exception("Fallo al devolver tarea: " . json_encode($res));
    }
});

// T14: [Verificación de Devolución] Tarea regresa atómicamente a 'En proceso' (columna 2) y fecha_finalizada es null
runTest("T14: [Verificación de Devolución] Tarea regresa a columna 2 (En proceso) y fecha_finalizada es null", function () use ($tarea1Id) {
    $t = Task::findById($tarea1Id);
    if ((int)$t['columna_id'] !== 2) {
        throw new Exception("La tarea debería haber regresado a columna 2 (En proceso), está en: " . $t['columna_id']);
    }
    if ($t['fecha_finalizada'] !== null) {
        throw new Exception("fecha_finalizada debería ser null al devolver la tarea");
    }
});

// T15: Tarea devuelta ya NO figura en bandeja de pendientes de jefatura
runTest("T15: Tarea devuelta ya no figura en bandeja de aprobaciones pendientes", function () use ($userJefe) {
    $approvalCtrl = new ApprovalController();
    $res = captureResponse(fn() => $approvalCtrl->pendientes(['user' => $userJefe]));

    if (!($res['success'] ?? false) || count($res['data']) !== 0) {
        throw new Exception("La bandeja debería estar vacía, se obtuvo: " . json_encode($res));
    }
});

// T16: Asistente 1 subsana y vuelve a solicitar aprobación -> 200 OK y tarea vuelve a 'En revisión'
runTest("T16: Asistente 1 subsana observaciones y vuelve a solicitar aprobación -> 200 OK", function () use ($userAsist1, $tarea1Id) {
    $approvalCtrl = new ApprovalController();
    $res = captureResponse(fn() => $approvalCtrl->solicitar([
        'user'   => $userAsist1,
        'params' => ['id' => $tarea1Id],
        'body'   => [
            'comentario_entrega' => 'Lote 3 reconciliado satisfactoriamente con contabilidad.'
        ]
    ]));

    if (!($res['success'] ?? false) || ($res['data']['estado'] ?? '') !== 'PENDIENTE') {
        throw new Exception("Fallo en re-solicitud: " . json_encode($res));
    }

    $t = Task::findById($tarea1Id);
    if ((int)$t['columna_id'] !== 4) {
        throw new Exception("La tarea debería volver a columna 4 (En revisión)");
    }
});

// T17: Jefe resuelve con APROBAR -> 200 OK
runTest("T17: Jefe aprueba formalmente la tarea -> 200 OK", function () use ($userJefe, $tarea1Id) {
    $approvalCtrl = new ApprovalController();
    $res = captureResponse(fn() => $approvalCtrl->resolver([
        'user'   => $userJefe,
        'params' => ['id' => $tarea1Id],
        'body'   => ['accion' => 'APROBAR']
    ]));

    if (!($res['success'] ?? false) || ($res['data']['estado'] ?? '') !== 'APROBADA') {
        throw new Exception("Fallo al aprobar tarea: " . json_encode($res));
    }
});

// T18: [Verificación de Aprobación RN-10] Tarea pasa a columna 5 (Finalizada) y fecha_finalizada es registrada
runTest("T18: [Verificación de Aprobación RN-10] Tarea pasa atómicamente a columna 5 (Finalizada) con fecha_finalizada", function () use ($tarea1Id) {
    $t = Task::findById($tarea1Id);
    if ((int)$t['columna_id'] !== 5) {
        throw new Exception("La tarea debería estar en columna 5 (Finalizada), está en: " . $t['columna_id']);
    }
    if (empty($t['fecha_finalizada'])) {
        throw new Exception("fecha_finalizada debería estar registrada al aprobar");
    }
});

// T19: Tarea aprobada ya no figura en bandeja de pendientes
runTest("T19: Tarea aprobada no figura en bandeja de pendientes", function () use ($userJefe) {
    $approvalCtrl = new ApprovalController();
    $res = captureResponse(fn() => $approvalCtrl->pendientes(['user' => $userJefe]));

    if (!($res['success'] ?? false) || count($res['data']) !== 0) {
        throw new Exception("La bandeja debería estar vacía tras aprobación");
    }
});

// T20: Historial GET /api/tareas/{id}/aprobaciones retorna 2 registros históricos (APROBADA y DEVUELTA)
runTest("T20: Historial de aprobaciones de la tarea retorna 2 registros históricos en orden cronológico", function () use ($userAsist1, $tarea1Id) {
    $approvalCtrl = new ApprovalController();
    $res = captureResponse(fn() => $approvalCtrl->historial([
        'user'   => $userAsist1,
        'params' => ['id' => $tarea1Id]
    ]));

    if (!($res['success'] ?? false) || count($res['data']) !== 2) {
        throw new Exception("Se esperaban 2 registros históricos, se obtuvo: " . json_encode($res));
    }

    $estados = array_column($res['data'], 'estado');
    if ($estados[0] !== 'APROBADA' || $estados[1] !== 'DEVUELTA') {
        throw new Exception("Secuencia de historial incorrecta: " . json_encode($estados));
    }
});

// T21: [Seguridad Anti-IDOR] Asistente 2 intenta ver historial de aprobaciones de tarea ajena -> 403 Forbidden
runTest("T21: [Seguridad Anti-IDOR] Asistente 2 intenta ver historial de aprobaciones ajeno -> 403 Forbidden", function () use ($userAsist2, $tarea1Id) {
    $approvalCtrl = new ApprovalController();
    $res = captureResponse(fn() => $approvalCtrl->historial([
        'user'   => $userAsist2,
        'params' => ['id' => $tarea1Id]
    ]));

    if (($res['error']['code'] ?? '') !== 'ERR_FORBIDDEN_TASK_ACCESS') {
        throw new Exception("Se esperaba ERR_FORBIDDEN_TASK_ACCESS, se obtuvo: " . json_encode($res));
    }
});

// T22: Trazabilidad en auditoría: la tabla auditoria_eventos registra todas las operaciones
runTest("T22: Trazabilidad en auditoría registra SOLICITAR_APROBACION, DEVOLVER y APROBAR", function () use ($pdo) {
    $stmt = $pdo->query("SELECT accion FROM auditoria_eventos WHERE entidad_tipo = 'APROBACION' ORDER BY id ASC");
    $acciones = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('SOLICITAR_APROBACION', $acciones) || !in_array('DEVOLVER', $acciones) || !in_array('APROBAR', $acciones)) {
        throw new Exception("Faltan acciones en auditoría de aprobaciones: " . json_encode($acciones));
    }
});

// T23: Despacho end-to-end con Router::dispatch para endpoints de aprobaciones
runTest("T23: Despacho end-to-end con Router para GET /api/aprobaciones/pendientes y GET /api/tareas/1/aprobaciones", function () use ($tokenJefe, $tarea1Id) {
    $router = new Router();
    $authMw = new AuthMiddleware();

    $router->get('/api/aprobaciones/pendientes', [ApprovalController::class, 'pendientes'], [$authMw]);
    $router->get('/api/tareas/{id}/aprobaciones', [ApprovalController::class, 'historial'], [$authMw]);

    $_COOKIE['auth_token'] = $tokenJefe;
    $_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$tokenJefe}";

    // Dispatch 1: Pendientes
    $res1 = captureResponse(fn() => $router->dispatch('GET', '/api/aprobaciones/pendientes'));
    if (!($res1['success'] ?? false)) {
        throw new Exception("Fallo en router dispatch pendientes: " . json_encode($res1));
    }

    // Dispatch 2: Historial
    $res2 = captureResponse(fn() => $router->dispatch('GET', "/api/tareas/{$tarea1Id}/aprobaciones"));
    if (!($res2['success'] ?? false) || count($res2['data']) !== 2) {
        throw new Exception("Fallo en router dispatch historial: " . json_encode($res2));
    }
});

// -----------------------------------------------------------------------------
// Resumen
// -----------------------------------------------------------------------------
echo "\n=== RESUMEN DE PRUEBAS MVP-06 ===\n";
echo "Total ejecutadas: {$testsCount}\n";
echo "Aprobadas:        {$testsPassed}\n";
echo "Fallidas:         " . ($testsCount - $testsPassed) . "\n\n";

if ($testsCount === $testsPassed) {
    echo ">>> RESULTADO: TODAS LAS PRUEBAS DE MVP-06 PASARON EXITOSAMENTE (100% PASS) <<<\n";
    exit(0);
} else {
    echo ">>> RESULTADO: ALGUNAS PRUEBAS FALLARON <<<\n";
    exit(1);
}
