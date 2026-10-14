<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Config\Database;
use App\Controllers\NotificationController;
use App\Core\Response;
use App\Models\Approval;
use App\Models\BoardColumn;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\Session;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\User;
use App\Services\AutomationEngine;

Response::$exitOnSend = false;

echo "=== INICIANDO SUITE DE PRUEBAS AUTOMATIZADAS: MVP-08 (AUTOMATIZACIONES Y NOTIFICACIONES IDEMPOTENTES) ===\n\n";

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
        tipo_estado TEXT NOT NULL UNIQUE,
        color_hex TEXT NOT NULL,
        activo INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL
    );

    CREATE TABLE tareas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        codigo TEXT NOT NULL UNIQUE,
        titulo TEXT NOT NULL,
        descripcion TEXT,
        columna_id INTEGER NOT NULL,
        tipo_tarea TEXT NOT NULL,
        prioridad TEXT NOT NULL,
        responsable_id INTEGER NOT NULL,
        creador_id INTEGER NOT NULL,
        requiere_aprobacion INTEGER NOT NULL DEFAULT 0,
        revisor_id INTEGER,
        fecha_limite TEXT,
        fecha_finalizada TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        FOREIGN KEY (columna_id) REFERENCES tableros_columnas(id),
        FOREIGN KEY (responsable_id) REFERENCES usuarios(id),
        FOREIGN KEY (creador_id) REFERENCES usuarios(id)
    );

    CREATE TABLE tarea_subtareas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tarea_id INTEGER NOT NULL,
        titulo TEXT NOT NULL,
        completada INTEGER NOT NULL DEFAULT 0,
        orden INTEGER NOT NULL DEFAULT 0,
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

    CREATE TABLE notificaciones (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        usuario_id INTEGER NOT NULL,
        tipo_evento TEXT NOT NULL,
        titulo TEXT NOT NULL,
        mensaje TEXT NOT NULL,
        entidad_tipo TEXT NOT NULL DEFAULT 'TAREA',
        entidad_id INTEGER NOT NULL,
        enlace TEXT,
        leida INTEGER NOT NULL DEFAULT 0,
        idempotency_key TEXT UNIQUE,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        leida_at TEXT,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    );
");

Database::setConnection($pdo);

// -----------------------------------------------------------------------------
// 2. Sembrar Datos Iniciales
// -----------------------------------------------------------------------------
$now = gmdate('Y-m-d H:i:s');
$passwordHash = password_hash('Planeamiento2026!', PASSWORD_BCRYPT);

$pdo->exec("
    INSERT INTO tableros_columnas (id, nombre, orden, tipo_estado, color_hex, activo, created_at) VALUES
    (1, 'Pendiente', 1, 'PENDIENTE', '#64748b', 1, '{$now}'),
    (2, 'En proceso', 2, 'EN_PROCESO', '#2563eb', 1, '{$now}'),
    (3, 'Bloqueada', 3, 'BLOQUEADA', '#e11d48', 1, '{$now}'),
    (4, 'En revisión', 4, 'EN_REVISION', '#d97706', 1, '{$now}'),
    (5, 'Finalizada', 5, 'FINALIZADA', '#059669', 1, '{$now}');
");

$pdo->exec("
    INSERT INTO usuarios (id, codigo, nombre, email, password_hash, rol, cargo, activo, created_at, updated_at) VALUES
    (1, 'ASIST-01', 'Marco Chumbes', 'mchumbes@planeamiento.tal', '{$passwordHash}', 'ASISTENTE', 'Asistente 1', 1, '{$now}', '{$now}'),
    (2, 'PLAN-01', 'Carlos Sanchez', 'planificador@planeamiento.tal', '{$passwordHash}', 'PLANIFICADOR', 'Planificador Senior', 1, '{$now}', '{$now}'),
    (3, 'JEFE-01', 'Elena Vega', 'jefe.planeamiento@planeamiento.tal', '{$passwordHash}', 'JEFE_PLANEAMIENTO', 'Jefe Planeamiento', 1, '{$now}', '{$now}');
");

$asist1Token = 'token_asist1_' . bin2hex(random_bytes(16));
$planToken   = 'token_plan1_' . bin2hex(random_bytes(16));
$jefeToken   = 'token_jefe1_' . bin2hex(random_bytes(16));

Session::create(1, $asist1Token);
Session::create(2, $planToken);
Session::create(3, $jefeToken);

$userAsist = User::findById(1);
$userPlan  = User::findById(2);
$userJefe  = User::findById(3);

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

// -----------------------------------------------------------------------------
// BLOQUE 1: Persistencia e Idempotencia Estricta (RN-15, RF-17)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 1: MODELO NOTIFICATION E IDEMPOTENCIA ESTRICTA (RN-15) ---\n";

$n1 = Notification::create(
    (int)$userPlan['id'],
    Notification::EVENTO_TAREA_ASIGNADA,
    "Nueva tarea asignada",
    "Se te ha asignado la tarea PLAN-001",
    'TAREA',
    10,
    'task_assign:10:2',
    'PLAN-001'
);

assertTest(!empty($n1['id']), "Creación exitosa de notificación persistida");
assertTest((int)$n1['leida'] === 0, "Notificación recién creada está sin leer (leida=0)");
assertTest($n1['tipo_evento'] === Notification::EVENTO_TAREA_ASIGNADA, "Tipo de evento asignado correctamente");
assertTest((int)$n1['entidad_id'] === 10, "Entidad ID registrada correctamente");

$unread1 = Notification::countUnread((int)$userPlan['id']);
assertTest($unread1 === 1, "Conteo de no leídas retorna 1", "Obtenido: {$unread1}");

// Disparo repetido con el mismo idempotency_key (RN-15)
$n1Repeat = Notification::create(
    (int)$userPlan['id'],
    Notification::EVENTO_TAREA_ASIGNADA,
    "Texto duplicado",
    "Texto duplicado que debe descartarse",
    'TAREA',
    10,
    'task_assign:10:2',
    'PLAN-001'
);

assertTest((int)$n1Repeat['id'] === (int)$n1['id'], "RN-15: Segundo disparo con la misma clave retorna registro idéntico");
$unread2 = Notification::countUnread((int)$userPlan['id']);
assertTest($unread2 === 1, "RN-15: No se crean registros duplicados ni incrementa contador de no leídas", "Obtenido: {$unread2}");

// -----------------------------------------------------------------------------
// BLOQUE 2: Lectura Individual y Protección Anti-IDOR
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 2: LECTURA INDIVIDUAL Y PROTECCIÓN ANTI-IDOR ---\n";

// Intento de marcado por usuario no autorizado (Marco intenta marcar notificación de Carlos)
$idorAttempt = Notification::markAsRead((int)$n1['id'], (int)$userAsist['id']);
assertTest($idorAttempt === false, "Anti-IDOR: Usuario ajeno no puede marcar como leída la notificación de otro");

$n1Fresh = Notification::findById((int)$n1['id']);
assertTest((int)$n1Fresh['leida'] === 0, "Notificación se mantiene no leída tras intento no autorizado");

// Propietario marca su notificación
$ownerMark = Notification::markAsRead((int)$n1['id'], (int)$userPlan['id']);
assertTest($ownerMark === true, "Propietario marca con éxito su propia notificación");

$n1Read = Notification::findById((int)$n1['id']);
assertTest((int)$n1Read['leida'] === 1 && !empty($n1Read['leida_at']), "Notificación queda registrada como leída con timestamp");
assertTest(Notification::countUnread((int)$userPlan['id']) === 0, "Contador de no leídas para el usuario desciende a 0");

// -----------------------------------------------------------------------------
// BLOQUE 3: Marcado Masivo de Notificaciones
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 3: MARCADO MASIVO (MARK ALL AS READ) ---\n";

Notification::create((int)$userPlan['id'], Notification::EVENTO_NUEVO_COMENTARIO, "Mensaje A", "Detalle A", 'COMENTARIO', 1, 'comm:1:2');
Notification::create((int)$userPlan['id'], Notification::EVENTO_NUEVO_COMENTARIO, "Mensaje B", "Detalle B", 'COMENTARIO', 2, 'comm:2:2');
Notification::create((int)$userPlan['id'], Notification::EVENTO_NUEVO_COMENTARIO, "Mensaje C", "Detalle C", 'COMENTARIO', 3, 'comm:3:2');

assertTest(Notification::countUnread((int)$userPlan['id']) === 3, "Se sembraron 3 notificaciones no leídas");

$affected = Notification::markAllAsRead((int)$userPlan['id']);
assertTest($affected === 3, "markAllAsRead retorna 3 registros afectados", "Afectados: {$affected}");
assertTest(Notification::countUnread((int)$userPlan['id']) === 0, "Contador de no leídas es 0 tras marcado masivo");

// -----------------------------------------------------------------------------
// BLOQUE 4: Disparo Automático: Asignación y Reasignación de Tarea (TAREA_ASIGNADA)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 4: DISPARO AUTOMÁTICO EN ASIGNACIÓN Y REASIGNACIÓN (RF-17) ---\n";

$colPendiente = BoardColumn::findByTipoEstado('PENDIENTE');
$t1 = Task::create([
    'titulo'         => 'Elaborar Cronograma Semanal',
    'descripcion'    => 'Planificación operativa',
    'columna_id'     => $colPendiente['id'],
    'tipo_tarea'     => 'OPERATIVA',
    'prioridad'      => 'ALTA',
    'responsable_id' => $userAsist['id']
], $userJefe);

$notifsAsist = Notification::listByUser((int)$userAsist['id']);
$notifAsignada = null;
foreach ($notifsAsist as $n) {
    if ($n['tipo_evento'] === Notification::EVENTO_TAREA_ASIGNADA && (int)$n['entidad_id'] === (int)$t1['id']) {
        $notifAsignada = $n;
        break;
    }
}

assertTest(!empty($notifAsignada), "Automation: TAREA_ASIGNADA disparada automáticamente al crear tarea");
assertTest(str_contains($notifAsignada['mensaje'], 'Elaborar Cronograma Semanal'), "Mensaje contiene título de tarea asignada");

// Reasignación de tarea al Planificador
Task::update((int)$t1['id'], [
    'responsable_id' => $userPlan['id']
], $userJefe);

$notifsPlan = Notification::listByUser((int)$userPlan['id']);
$notifReasig = null;
foreach ($notifsPlan as $n) {
    if ($n['tipo_evento'] === Notification::EVENTO_TAREA_ASIGNADA && (int)$n['entidad_id'] === (int)$t1['id']) {
        $notifReasig = $n;
        break;
    }
}

assertTest(!empty($notifReasig), "Automation: TAREA_ASIGNADA disparada automáticamente al reasignar responsable");

// -----------------------------------------------------------------------------
// BLOQUE 5: Disparo Automático: Solicitud de Aprobación Formal (APROBACION_SOLICITADA)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 5: DISPARO AUTOMÁTICO EN SOLICITUD DE APROBACIÓN (RF-17) ---\n";

$colEnProc = BoardColumn::findByTipoEstado('EN_PROCESO');
$pdo->prepare("UPDATE tareas SET columna_id = :cid, requiere_aprobacion = 1 WHERE id = :id")
    ->execute([':cid' => $colEnProc['id'], ':id' => $t1['id']]);

$aprobacion = Approval::solicitar((int)$t1['id'], (int)$userPlan['id'], 'Listo para revisión formal de jefatura', (int)$userJefe['id']);

$notifsJefe = Notification::listByUser((int)$userJefe['id']);
$notifReq = null;
foreach ($notifsJefe as $n) {
    if ($n['tipo_evento'] === Notification::EVENTO_APROBACION_SOLICITADA && (int)$n['entidad_id'] === (int)$t1['id']) {
        $notifReq = $n;
        break;
    }
}

assertTest(!empty($notifReq), "Automation: APROBACION_SOLICITADA notificada al jefe revisor");
assertTest(str_contains($notifReq['mensaje'], 'aprobación formal'), "Mensaje describe correctamente la solicitud");

// -----------------------------------------------------------------------------
// BLOQUE 6: Disparo Automático: Dictamen de Devolución (TAREA_DEVUELTA)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 6: DISPARO AUTOMÁTICO EN DICTAMEN DE DEVOLUCIÓN (RF-17) ---\n";

$devuelta = Approval::resolver((int)$t1['id'], (int)$userJefe['id'], 'DEVOLVER', 'Falta incluir detalle de hitos');

$notifsPlanPostDev = Notification::listByUser((int)$userPlan['id']);
$notifDev = null;
foreach ($notifsPlanPostDev as $n) {
    if ($n['tipo_evento'] === Notification::EVENTO_TAREA_DEVUELTA && (int)$n['entidad_id'] === (int)$t1['id']) {
        $notifDev = $n;
        break;
    }
}

assertTest(!empty($notifDev), "Automation: TAREA_DEVUELTA notificada al responsable solicitante");
assertTest(str_contains($notifDev['mensaje'], 'Falta incluir detalle de hitos'), "Mensaje de devolución incluye observaciones de subsanación");

// -----------------------------------------------------------------------------
// BLOQUE 7: Disparo Automático: Aprobación Formal (TAREA_APROBADA)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 7: DISPARO AUTOMÁTICO EN APROBACIÓN FORMAL (RF-17) ---\n";

Approval::solicitar((int)$t1['id'], (int)$userPlan['id'], 'Hitos agregados', (int)$userJefe['id']);
Approval::resolver((int)$t1['id'], (int)$userJefe['id'], 'APROBAR');

$notifsPlanPostApr = Notification::listByUser((int)$userPlan['id']);
$notifApr = null;
foreach ($notifsPlanPostApr as $n) {
    if ($n['tipo_evento'] === Notification::EVENTO_TAREA_APROBADA && (int)$n['entidad_id'] === (int)$t1['id']) {
        $notifApr = $n;
        break;
    }
}

assertTest(!empty($notifApr), "Automation: TAREA_APROBADA notificada al responsable de la tarea");

// -----------------------------------------------------------------------------
// BLOQUE 8: Disparo Automático: Desbloqueo en Cascada DAG (TAREA_DESBLOQUEADA)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 8: DISPARO AUTOMÁTICO EN DESBLOQUEO EN CASCADA DAG (RF-17) ---\n";

$colBloq = BoardColumn::findByTipoEstado('BLOQUEADA');
$tPred = Task::create([
    'titulo'         => 'Revisión Preliminar de Datos',
    'descripcion'    => 'Predecesora',
    'columna_id'     => $colEnProc['id'],
    'tipo_tarea'     => 'OPERATIVA',
    'prioridad'      => 'MEDIA',
    'responsable_id' => $userJefe['id']
], $userJefe);

$tSucc = Task::create([
    'titulo'         => 'Publicación Final de Resultados',
    'descripcion'    => 'Sucesora bloqueada',
    'columna_id'     => $colBloq['id'],
    'tipo_tarea'     => 'ESTRATEGICA',
    'prioridad'      => 'ALTA',
    'responsable_id' => $userAsist['id']
], $userJefe);

TaskDependency::add((int)$tSucc['id'], (int)$tPred['id'], $userJefe);

// Finalizar la predecesora
$colFin = BoardColumn::findByTipoEstado('FINALIZADA');
$pdo->prepare("UPDATE tareas SET columna_id = :cid, fecha_finalizada = :now WHERE id = :id")
    ->execute([':cid' => $colFin['id'], ':now' => gmdate('Y-m-d H:i:s'), ':id' => $tPred['id']]);

TaskDependency::onTaskCompleted((int)$tPred['id'], $userJefe);

$notifsAsistPostUnlock = Notification::listByUser((int)$userAsist['id']);
$notifUnlock = null;
foreach ($notifsAsistPostUnlock as $n) {
    if ($n['tipo_evento'] === Notification::EVENTO_TAREA_DESBLOQUEADA && (int)$n['entidad_id'] === (int)$tSucc['id']) {
        $notifUnlock = $n;
        break;
    }
}

assertTest(!empty($notifUnlock), "Automation: TAREA_DESBLOQUEADA notificada al responsable de la sucesora liberada");

// -----------------------------------------------------------------------------
// BLOQUE 9: Disparo Automático: Nuevo Comentario (NUEVO_COMENTARIO)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 9: DISPARO AUTOMÁTICO EN COMENTARIOS (RF-17) ---\n";

// Marco (Asistente) comenta en la tarea de Carlos (Planificador)
$comment = Comment::create((int)$t1['id'], (int)$userAsist['id'], "Marco comenta: por favor revisar los anexos.");

$notifsPlanPostComm = Notification::listByUser((int)$userPlan['id']);
$notifComm = null;
foreach ($notifsPlanPostComm as $n) {
    if ($n['tipo_evento'] === Notification::EVENTO_NUEVO_COMENTARIO && (int)$n['entidad_id'] === (int)$t1['id']) {
        $notifComm = $n;
        break;
    }
}

assertTest(!empty($notifComm), "Automation: NUEVO_COMENTARIO notificado al responsable asignado");
assertTest(str_contains($notifComm['mensaje'], 'Marco Chumbes'), "Mensaje contiene el nombre del autor del comentario");

// Autor no recibe notificación de su propio comentario
$asistSelfNotif = array_filter(
    Notification::listByUser((int)$userAsist['id']),
    fn($n) => $n['tipo_evento'] === Notification::EVENTO_NUEVO_COMENTARIO && (int)$n['entidad_id'] === (int)$t1['id']
);
assertTest(empty($asistSelfNotif), "Automation: Autor del comentario no es auto-notificado");

// -----------------------------------------------------------------------------
// BLOQUE 10: Controlador y Endpoints HTTP
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 10: CONTROLADOR Y ENDPOINTS HTTP (RF-17) ---\n";

$notifCtrl = new NotificationController();

// 1. GET /api/notificaciones/resumen
ob_start();
$notifCtrl->unreadCount(['user' => $userPlan]);
$outUnread = ob_get_clean();
$jsonUnread = json_decode($outUnread, true);

assertTest(isset($jsonUnread['data']['no_leidas']), "GET /api/notificaciones/resumen retorna campo 'no_leidas'");
assertTest(isset($jsonUnread['data']['recientes']) && is_array($jsonUnread['data']['recientes']), "GET /api/notificaciones/resumen retorna array 'recientes'");

// 2. GET /api/notificaciones
ob_start();
$notifCtrl->index(['user' => $userPlan, 'query' => []]);
$outIndex = ob_get_clean();
$jsonIndex = json_decode($outIndex, true);

assertTest(isset($jsonIndex['data']['notificaciones']) && is_array($jsonIndex['data']['notificaciones']), "GET /api/notificaciones retorna listado");
assertTest(isset($jsonIndex['data']['no_leidas']), "Listado incluye conteo de no leídas");

// 3. POST /api/notificaciones/marcar-todas-leidas
ob_start();
$notifCtrl->markAllAsRead(['user' => $userPlan]);
$outMarkAll = ob_get_clean();
$jsonMarkAll = json_decode($outMarkAll, true);

assertTest(isset($jsonMarkAll['data']['mensaje']) && isset($jsonMarkAll['data']['total_actualizadas']), "POST /api/notificaciones/marcar-todas-leidas ejecuta y confirma");

// 4. Verificar que ahora no_leidas sea 0
ob_start();
$notifCtrl->unreadCount(['user' => $userPlan]);
$outUnread2 = ob_get_clean();
$jsonUnread2 = json_decode($outUnread2, true);

assertTest(($jsonUnread2['data']['no_leidas'] ?? -1) === 0, "Tras marcar todas como leídas, unreadCount retorna 0");

// -----------------------------------------------------------------------------
// Resumen Final
// -----------------------------------------------------------------------------
echo "\n=======================================================\n";
echo "RESULTADO SUITE MVP-08: {$testsPassed} de {$testsCount} tests aprobados (" . round(($testsPassed / $testsCount) * 100) . "%)\n";
echo "=======================================================\n\n";

if ($testsPassed === $testsCount) {
    echo ">>> TODOS LOS TESTS DE AUTOMATIZACIONES Y NOTIFICACIONES IDEMPOTENTES (MVP-08) PASARON EXITOSAMENTE <<<\n\n";
    exit(0);
} else {
    echo ">>> EXISTEN TESTS FALLIDOS EN MVP-08 <<<\n\n";
    exit(1);
}
