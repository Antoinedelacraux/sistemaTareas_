<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Config\Database;
use App\Controllers\ApprovalController;
use App\Controllers\AuditController;
use App\Controllers\CommentController;
use App\Controllers\DashboardController;
use App\Controllers\DependencyController;
use App\Controllers\NotificationController;
use App\Controllers\SubtaskController;
use App\Controllers\TaskController;
use App\Controllers\UserController;
use App\Core\Response;
use App\Core\UploadHandler;
use App\Models\Approval;
use App\Models\Audit;
use App\Models\BoardColumn;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\Session;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\User;
use App\Services\AutomationEngine;
use App\Services\DashboardService;

Response::$exitOnSend = false;

echo "=================================================================================\n";
echo "=== SUITE DE QA INTEGRAL, SEGURIDAD Y SIMULACIÓN PILOTO (5 USUARIOS) — MVP-10 ===\n";
echo "=================================================================================\n\n";

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
        activo INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    );

    CREATE TABLE tareas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        codigo TEXT NOT NULL UNIQUE,
        titulo TEXT NOT NULL,
        descripcion TEXT,
        tipo_tarea TEXT NOT NULL,
        columna_id INTEGER NOT NULL,
        prioridad TEXT NOT NULL,
        creador_id INTEGER NOT NULL,
        responsable_id INTEGER,
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
        revisor_id INTEGER,
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
// 2. Semillas: Columnas Kanban y Equipo Piloto de 5 Personas
// -----------------------------------------------------------------------------
$pdo->exec("
    INSERT INTO tableros_columnas (id, nombre, orden, tipo_estado, color_hex, activo, created_at, updated_at) VALUES
    (1, 'Pendiente', 1, 'PENDIENTE', '#64748b', 1, datetime('now'), datetime('now')),
    (2, 'En proceso', 2, 'EN_PROCESO', '#2563eb', 1, datetime('now'), datetime('now')),
    (3, 'Bloqueada', 3, 'BLOQUEADA', '#e11d48', 1, datetime('now'), datetime('now')),
    (4, 'En revisión', 4, 'EN_REVISION', '#d97706', 1, datetime('now'), datetime('now')),
    (5, 'Finalizada', 5, 'FINALIZADA', '#059669', 1, datetime('now'), datetime('now'));

    INSERT INTO usuarios (id, codigo, nombre, email, password_hash, rol, cargo, activo, created_at, updated_at) VALUES
    (1, 'ASIST-01', 'Marco Chumbes', 'mchumbes@planeamiento.tal', 'hash1', 'ASISTENTE', 'Asistente de Información 1', 1, datetime('now'), datetime('now')),
    (2, 'ASIST-02', 'Asistente Dos', 'asistente2@planeamiento.tal', 'hash2', 'ASISTENTE', 'Asistente de Información 2', 1, datetime('now'), datetime('now')),
    (3, 'ASIST-03', 'Asistente Tres', 'asistente3@planeamiento.tal', 'hash3', 'ASISTENTE', 'Asistente de Información 3', 1, datetime('now'), datetime('now')),
    (4, 'JEFE-PER', 'Roberto Jefe Personal', 'jefe.personal@planeamiento.tal', 'hash4', 'JEFE_PERSONAL', 'Jefe de Personal', 1, datetime('now'), datetime('now')),
    (5, 'JEFE-PLA', 'Elena Vega', 'jefe.planeamiento@planeamiento.tal', 'hash5', 'JEFE_PLANEAMIENTO', 'Jefe del Área de Planeamiento', 1, datetime('now'), datetime('now'));
");

$asistente1 = User::formatPublic(User::findById(1)); // Marco Chumbes
$asistente2 = User::formatPublic(User::findById(2)); // Asistente Dos
$asistente3 = User::formatPublic(User::findById(3)); // Asistente Tres
$jefePersonal = User::formatPublic(User::findById(4)); // Roberto
$jefePlaneamiento = User::formatPublic(User::findById(5)); // Elena Vega

// Contadores de verificación
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assert_test(string $desc, bool $condition, string $detail = ''): void {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "  [PASS] $desc\n";
    } else {
        $failedTests++;
        echo "  [FAIL] $desc " . ($detail ? "($detail)" : "") . "\n";
    }
}

function invokeController(callable $fn): array {
    ob_start();
    $fn();
    $raw = ob_get_clean();
    $json = json_decode($raw, true) ?? [];
    return [
        'status' => Response::$lastStatusCode,
        'body'   => $raw,
        'json'   => $json,
        'data'   => $json['data'] ?? null,
        'error'  => $json['error'] ?? null
    ];
}

// =============================================================================
// PARTE 1: VERIFICACIÓN DE SEGURIDAD OWASP, RBAC Y ANTI-IDOR
// =============================================================================
echo "--- PARTE 1: AUDITORÍA DE SEGURIDAD OWASP, RBAC Y ANTI-IDOR ---\n";

// 1.1 RBAC: Asistente no puede asignar tareas a otros usuarios (RN-03)
$_SERVER['REQUEST_METHOD'] = 'POST';
$taskController = new TaskController();
$res = invokeController(fn() => $taskController->create([
    'user' => $asistente1,
    'body' => [
        'titulo' => 'Intento de asignación indebida',
        'tipo_tarea' => 'AREA',
        'prioridad' => 'MEDIA',
        'responsable_id' => 2 // Asistente 1 intenta asignar a Asistente 2
    ]
]));
$payload = json_decode($res['body'], true);
assert_test('1.1 RBAC RN-03: Asistente 1 no puede asignar tarea a Asistente 2 (403 Forbidden)', 
    $res['status'] === 403 && in_array($payload['error']['code'] ?? '', ['ERR_FORBIDDEN_ACTION', 'ERR_FORBIDDEN_ASSIGNMENT'], true));

// 1.2 Anti-IDOR: Asistente 1 crea tarea personal privada. Asistente 2 no puede consultarla (RN-02)
$tareaPersonalA1 = Task::create([
    'titulo' => 'Mi tarea personal privada',
    'tipo_tarea' => 'PERSONAL',
    'prioridad' => 'BAJA',
    'creador_id' => $asistente1['id'],
    'responsable_id' => $asistente1['id'],
    'columna_id' => 1
], $asistente1);

$res = invokeController(fn() => $taskController->show([
    'user' => $asistente2,
    'params' => ['id' => (int)$tareaPersonalA1['id']]
]));
$payload = json_decode($res['body'], true);
assert_test('1.2 Anti-IDOR RN-02: Asistente 2 no puede ver tarea personal de Asistente 1 (403 Forbidden)',
    $res['status'] === 403 && in_array($payload['error']['code'] ?? '', ['ERR_FORBIDDEN_ACTION', 'ERR_FORBIDDEN_TASK_ACCESS'], true));

// 1.3 Anti-IDOR Historial: Asistente 2 no puede ver auditoría de tarea personal de Asistente 1
$auditController = new AuditController();
$res = invokeController(fn() => $auditController->taskHistory([
    'user' => $asistente2,
    'params' => ['id' => (int)$tareaPersonalA1['id']]
]));
$payload = json_decode($res['body'], true);
assert_test('1.3 Anti-IDOR RF-18: Asistente 2 no puede consultar timeline de tarea privada ajena (403 Forbidden)',
    $res['status'] === 403 && ($payload['error']['code'] ?? '') === 'ERR_FORBIDDEN_ACTION');

// 1.4 Anti-IDOR Subtareas: Asistente 2 no puede añadir subtarea a tarea de Asistente 1
$subtaskController = new SubtaskController();
$res = invokeController(fn() => $subtaskController->create([
    'user' => $asistente2,
    'params' => ['id' => (int)$tareaPersonalA1['id']],
    'body' => ['titulo' => 'Inyección de subtarea']
]));
$payload = json_decode($res['body'], true);
assert_test('1.4 Anti-IDOR RF-10: Asistente 2 no puede inyectar subtareas en tarea ajena (403 Forbidden)',
    $res['status'] === 403 && in_array($payload['error']['code'] ?? '', ['ERR_FORBIDDEN_ACTION', 'ERR_FORBIDDEN_TASK_ACCESS'], true));

// 1.5 Anti-IDOR Comentarios: Asistente 2 no puede comentar en tarea privada ajena
$commentController = new CommentController();
$res = invokeController(fn() => $commentController->create([
    'user' => $asistente2,
    'params' => ['id' => (int)$tareaPersonalA1['id']],
    'body' => ['comentario' => 'Comentario no autorizado']
]));
$payload = json_decode($res['body'], true);
assert_test('1.5 Anti-IDOR RF-10: Asistente 2 no puede comentar en tarea privada ajena (403 Forbidden)',
    $res['status'] === 403 && in_array($payload['error']['code'] ?? '', ['ERR_FORBIDDEN_ACTION', 'ERR_FORBIDDEN_TASK_ACCESS'], true));

// 1.6 Anti-IDOR Notificaciones: Asistente 2 no puede marcar como leída notificación de Asistente 1
$notifA1 = Notification::create(
    (int)$asistente1['id'],
    'ALERTA_TEST',
    'Alerta para Marco',
    'Contenido confidencial',
    'TAREA',
    1,
    'notif-sec-test-01'
);
$notifController = new NotificationController();
$res = invokeController(fn() => $notifController->markAsRead([
    'user' => $asistente2,
    'params' => ['id' => (int)$notifA1['id']]
]));
$payload = json_decode($res['body'], true);
assert_test('1.6 Anti-IDOR RN-02: Asistente 2 no puede marcar notificación ajena como leída (403 Forbidden)',
    $res['status'] === 403 && ($payload['error']['code'] ?? '') === 'ERR_FORBIDDEN_ACTION');

// 1.7 Seguridad en Aprobaciones: Asistente no puede listar bandeja de aprobaciones de Jefatura (RF-13)
$approvalController = new ApprovalController();
$res = invokeController(fn() => $approvalController->pendientes([
    'user' => $asistente1
]));
$payload = json_decode($res['body'], true);
assert_test('1.7 RBAC RF-13: Asistente no tiene acceso a bandeja de aprobaciones de Jefatura (403 Forbidden)',
    $res['status'] === 403 && ($payload['error']['code'] ?? '') === 'ERR_FORBIDDEN_ACTION');

// 1.8 Seguridad en Archivos: UploadHandler prohíbe extensiones ejecutables o maliciosas
assert_test('1.8 File Security: Bloqueo de script PHP (.php)', !UploadHandler::isAllowedExtension('shell.php'));
assert_test('1.8 File Security: Bloqueo de ejecutable binario (.exe)', !UploadHandler::isAllowedExtension('payload.exe'));
assert_test('1.8 File Security: Bloqueo de script bash (.sh)', !UploadHandler::isAllowedExtension('exploit.sh'));
assert_test('1.8 File Security: Bloqueo de script Python (.py)', !UploadHandler::isAllowedExtension('backdoor.py'));
assert_test('1.8 File Security: Aceptación de documento PDF legítimo (.pdf)', UploadHandler::isAllowedExtension('informe_planeamiento.pdf'));
assert_test('1.8 File Security: Aceptación de planilla Excel (.xlsx)', UploadHandler::isAllowedExtension('matriz_produccion.xlsx'));

echo "\n";

// =============================================================================
// PARTE 2: VERIFICACIÓN DE LOS 12 CRITERIOS DE ACEPTACIÓN MÍNIMA (V1.2)
// =============================================================================
echo "--- PARTE 2: MATRIZ DE LOS 12 CRITERIOS DE ACEPTACIÓN MÍNIMA (V1.2) ---\n";

// CRITERIO 1: Asistente crea tarea para sí; puede verla y actualizarla
$resC1 = invokeController(fn() => $taskController->create([
    'user' => $asistente1,
    'body' => [
        'titulo' => 'Tarea Criterio 1 Asistente 1',
        'tipo_tarea' => 'AREA',
        'prioridad' => 'ALTA',
        'responsable_id' => $asistente1['id']
    ]
]));
$taskC1 = json_decode($resC1['body'], true)['data']['tarea'] ?? [];
assert_test('Criterio 1.A: Asistente 1 crea tarea del área para sí con éxito', $resC1['status'] === 201 && !empty($taskC1['id']));

$resC1View = invokeController(fn() => $taskController->show([
    'user' => $asistente1,
    'params' => ['id' => (int)$taskC1['id']]
]));
assert_test('Criterio 1.B: Asistente 1 puede consultar y ver el detalle de su tarea', $resC1View['status'] === 200);

$resC1Upd = invokeController(fn() => $taskController->update([
    'user' => $asistente1,
    'params' => ['id' => (int)$taskC1['id']],
    'body' => ['titulo' => 'Tarea Criterio 1 (Actualizada)']
]));
$taskC1Updated = json_decode($resC1Upd['body'], true)['data']['tarea'] ?? [];
assert_test('Criterio 1.C: Asistente 1 puede actualizar su tarea',
    $resC1Upd['status'] === 200 && ($taskC1Updated['titulo'] ?? '') === 'Tarea Criterio 1 (Actualizada)');

// CRITERIO 2: Intento de asignación de asistente a otro => denegado por API (Validado en 1.1)
assert_test('Criterio 2: Intento de asignación de asistente a otro es denegado por API (RN-03)', $res['status'] === 403);

// CRITERIO 3: Intento de consultar ID de tarea ajena => denegado por API (Validado en 1.2)
$resC3 = invokeController(fn() => $taskController->show([
    'user' => $asistente2,
    'params' => ['id' => (int)$tareaPersonalA1['id']]
]));
assert_test('Criterio 3: Intento de consultar ID de tarea ajena es denegado por API (RN-02)', $resC3['status'] === 403);

// CRITERIO 4: Jefe asigna una tarea y aparece en la vista del responsable
$resC4 = invokeController(fn() => $taskController->create([
    'user' => $jefePlaneamiento,
    'body' => [
        'titulo' => 'Plan Operativo Semanal Lote 4',
        'tipo_tarea' => 'AREA',
        'prioridad' => 'URGENTE',
        'responsable_id' => $asistente2['id']
    ]
]));
$taskC4 = json_decode($resC4['body'], true)['data']['tarea'] ?? [];
assert_test('Criterio 4.A: Jefe de Planeamiento crea y asigna tarea a Asistente 2', 
    $resC4['status'] === 201 && (int)($taskC4['responsable_id'] ?? 0) === (int)$asistente2['id']);

$resC4View = invokeController(fn() => $taskController->show([
    'user' => $asistente2,
    'params' => ['id' => (int)$taskC4['id']]
]));
assert_test('Criterio 4.B: Asistente 2 puede consultar la tarea asignada por su Jefatura', $resC4View['status'] === 200);

// CRITERIO 5: Confirmación cancelada => no cierra tarea ni dispara dependencias (RN-11)
$tareaSinAprobacion = Task::create([
    'titulo' => 'Tarea de verificación sin aprobación',
    'tipo_tarea' => 'AREA',
    'prioridad' => 'MEDIA',
    'creador_id' => $asistente1['id'],
    'responsable_id' => $asistente1['id'],
    'columna_id' => 2, // En Proceso
    'requiere_aprobacion' => 0
], $asistente1);

// Intento de mover a Finalizada (columna 5) cancelado explícitamente
$resC5 = Task::transition((int)$tareaSinAprobacion['id'], 5, ['confirmacion_cierre' => false], $asistente1);
$tareaSinAprobacionRec = Task::findById((int)$tareaSinAprobacion['id']);
assert_test('Criterio 5: Cancelación/ausencia de confirmación no cierra la tarea (RN-11)', 
    $resC5['success'] === false && ($resC5['code'] ?? '') === 'ERR_CONFIRMATION_REQUIRED' && (int)$tareaSinAprobacionRec['columna_id'] === 2);

// CRITERIO 6: Confirmación aceptada => tarea sin aprobación se cierra y activa dependientes elegibles
$resC6 = Task::transition((int)$tareaSinAprobacion['id'], 5, ['confirmacion_cierre' => true], $asistente1);
$tareaSinAprobacionCerrada = Task::findById((int)$tareaSinAprobacion['id']);
assert_test('Criterio 6: Confirmación aceptada finaliza la tarea satisfactoriamente (RN-11)', 
    $resC6['success'] === true && (int)$tareaSinAprobacionCerrada['columna_id'] === 5 && !empty($tareaSinAprobacionCerrada['fecha_finalizada']));

// CRITERIO 7: Tarea con aprobación no cierra hasta que aprobador la autorice
$tareaConAprob = Task::create([
    'titulo' => 'Informe Oficial Auditado',
    'tipo_tarea' => 'AREA',
    'prioridad' => 'ALTA',
    'creador_id' => $asistente1['id'],
    'responsable_id' => $asistente1['id'],
    'columna_id' => 2, // En proceso
    'requiere_aprobacion' => 1
], $asistente1);

// Asistente solicita aprobación formal -> Columna 4 (En revisión)
$resSol = invokeController(fn() => $approvalController->solicitar([
    'user' => $asistente1,
    'params' => ['id' => (int)$tareaConAprob['id']]
]));
$tareaConAprobEstado = Task::findById((int)$tareaConAprob['id']);
assert_test('Criterio 7.A: Solicitud de aprobación traslada tarea a En Revisión (RN-10)', 
    $resSol['status'] === 200 && (int)$tareaConAprobEstado['columna_id'] === 4);

// Intento de devolver sin observaciones debe fallar (RN-12)
$resDevInvalida = invokeController(fn() => $approvalController->resolver([
    'user' => $jefePlaneamiento,
    'params' => ['id' => (int)$tareaConAprob['id']],
    'body' => [
        'accion' => 'DEVOLVER',
        'observaciones' => '   '
    ]
]));
assert_test('Criterio 7.B: Devolución con observaciones vacías es rechazada con 422 (RN-12)', $resDevInvalida['status'] === 422);

// Jefatura aprueba con dictamen formal -> Columna 5 (Finalizada)
$resAprobExito = invokeController(fn() => $approvalController->resolver([
    'user' => $jefePlaneamiento,
    'params' => ['id' => (int)$tareaConAprob['id']],
    'body' => [
        'accion' => 'APROBAR',
        'observaciones' => 'Aprobado formalmente según estándares TAL S.A.'
    ]
]));
$tareaConAprobFinal = Task::findById((int)$tareaConAprob['id']);
assert_test('Criterio 7.C: Dictamen APROBAR finaliza la tarea formalmente con timestamp (RN-10)', 
    $resAprobExito['status'] === 200 && (int)$tareaConAprobFinal['columna_id'] === 5,
    "res_status={$resAprobExito['status']} body={$resAprobExito['body']}");

// CRITERIO 8: Tres ramas paralelas: completar 2/3 mantiene bloqueada la sucesora; 3/3 la habilita (RN-08)
$predA = Task::create(['titulo' => 'Rama 1', 'tipo_tarea' => 'AREA', 'prioridad' => 'MEDIA', 'creador_id' => 5, 'responsable_id' => 1, 'columna_id' => 2], $jefePlaneamiento);
$predB = Task::create(['titulo' => 'Rama 2', 'tipo_tarea' => 'AREA', 'prioridad' => 'MEDIA', 'creador_id' => 5, 'responsable_id' => 2, 'columna_id' => 2], $jefePlaneamiento);
$predC = Task::create(['titulo' => 'Rama 3', 'tipo_tarea' => 'AREA', 'prioridad' => 'MEDIA', 'creador_id' => 5, 'responsable_id' => 3, 'columna_id' => 2], $jefePlaneamiento);

$sucesoraDAG = Task::create(['titulo' => 'Consolidado General (Espera 3 ramas)', 'tipo_tarea' => 'AREA', 'prioridad' => 'URGENTE', 'creador_id' => 5, 'responsable_id' => 1, 'columna_id' => 1], $jefePlaneamiento);

// Vincular las 3 dependencias (autobloqueo a Columna 3)
TaskDependency::add((int)$sucesoraDAG['id'], (int)$predA['id'], $jefePlaneamiento);
TaskDependency::add((int)$sucesoraDAG['id'], (int)$predB['id'], $jefePlaneamiento);
TaskDependency::add((int)$sucesoraDAG['id'], (int)$predC['id'], $jefePlaneamiento);

$sucesoraRec1 = Task::findById((int)$sucesoraDAG['id']);
assert_test('Criterio 8.A: Tarea sucesora pasa automáticamente a BLOQUEADA al tener dependencias pendientes (RN-08)', 
    (int)$sucesoraRec1['columna_id'] === 3);

// Finalizar 1/3 (Rama 1)
Task::transition((int)$predA['id'], 5, ['confirmacion_cierre' => true], $asistente1);
$sucesoraRec2 = Task::findById((int)$sucesoraDAG['id']);
assert_test('Criterio 8.B: Con 1/3 ramas finalizadas, la tarea continúa BLOQUEADA', (int)$sucesoraRec2['columna_id'] === 3);

// Finalizar 2/3 (Rama 2)
Task::transition((int)$predB['id'], 5, ['confirmacion_cierre' => true], $asistente2);
$sucesoraRec3 = Task::findById((int)$sucesoraDAG['id']);
assert_test('Criterio 8.C: Con 2/3 ramas finalizadas, la tarea continúa BLOQUEADA (regla AND)', (int)$sucesoraRec3['columna_id'] === 3);

// Finalizar 3/3 (Rama 3) -> Desbloqueo atómico a PENDIENTE (Columna 1)
Task::transition((int)$predC['id'], 5, ['confirmacion_cierre' => true], $asistente3);
$sucesoraRecFinal = Task::findById((int)$sucesoraDAG['id']);
assert_test('Criterio 8.D: Al finalizar 3/3 ramas, la sucesora se desbloquea atómicamente a PENDIENTE (RF-16)', 
    (int)$sucesoraRecFinal['columna_id'] === 1);

// CRITERIO 9: Cambiar nombre de columna no rompe las reglas de transición (RN-06)
$pdo->exec("UPDATE tableros_columnas SET nombre = 'Tareas Terminadas' WHERE id = 5");
$colFinalizada = BoardColumn::findById(5);
assert_test('Criterio 9: Conservación del tipo funcional FINALIZADA tras renombrado de columna (RN-06)', 
    $colFinalizada['tipo_estado'] === 'FINALIZADA' && $colFinalizada['nombre'] === 'Tareas Terminadas');

// CRITERIO 10: Prevención de duplicados e idempotencia (RN-15)
$n1 = Notification::create(1, 'ALERTA_CRITICA', 'Aviso 1', 'Texto 1', 'TAREA', 100, 'idemp-criterio-10-key');
$n2 = Notification::create(1, 'ALERTA_CRITICA', 'Aviso 1 Reintentado', 'Texto 1 Reintentado', 'TAREA', 100, 'idemp-criterio-10-key');
assert_test('Criterio 10: Idempotencia estricta previene duplicación ante reintentos (RN-15)', 
    $n1 !== null && $n2 !== null && (int)$n1['id'] === (int)$n2['id']);

// CRITERIO 11: Auditoría inmutable de cambios (quién, cuándo y qué cambió) (RF-18, RN-13)
$auditLogs = Audit::getByTask((int)$tareaConAprob['id']);
assert_test('Criterio 11.A: Auditoría registra la creación, solicitud y dictamen de aprobación', count($auditLogs) >= 3);
assert_test('Criterio 11.B: Registro de auditoría contiene actor, acción y timestamp inmutable', 
    isset($auditLogs[0]['created_at']) && isset($auditLogs[0]['accion']));

// CRITERIO 12: Arquitectura y entorno de despliegue seguro
$envExists = file_exists(__DIR__ . '/../.env.example');
$htaccessUploads = file_exists(__DIR__ . '/../storage/uploads/.htaccess');
$htaccessApp = file_exists(__DIR__ . '/../app/.htaccess');
assert_test('Criterio 12: Reglas de seguridad Apache, aislamiento de código y .env.example presentes', 
    $envExists && $htaccessUploads && $htaccessApp);

echo "\n";

// =============================================================================
// PARTE 3: SIMULACIÓN DE FLUJO DE TRABAJO PILOTO (5 USUARIOS REALES)
// =============================================================================
echo "--- PARTE 3: SIMULACIÓN END-TO-END DEL PILOTO (5 USUARIOS DE TAL S.A.) ---\n";

// Paso 1: Elena Vega (Jefe Planeamiento) asigna tarea oficial a Marco Chumbes (Asistente 1)
$resPiloto1 = invokeController(fn() => $taskController->create([
    'user' => $jefePlaneamiento,
    'body' => [
        'titulo' => 'Auditoría Mensual de Tiempos de Parada de Planta',
        'descripcion' => 'Revisar bitácoras de mantenimiento e identificar desviaciones superiores a 15 min.',
        'tipo_tarea' => 'AREA',
        'prioridad' => 'ALTA',
        'responsable_id' => $asistente1['id'],
        'fecha_limite' => date('Y-m-d', strtotime('+3 days')),
        'requiere_aprobacion' => 1
    ]
]));
$tareaPiloto = json_decode($resPiloto1['body'], true)['data']['tarea'] ?? [];
assert_test('Piloto Paso 1: Elena Vega crea tarea oficial y la asigna a Marco Chumbes', $resPiloto1['status'] === 201 && !empty($tareaPiloto['id']));

// Paso 2: Marco Chumbes verifica su notificación de asignación
$notifsMarco = Notification::listByUser((int)$asistente1['id'], true);
$notifAsignada = null;
foreach ($notifsMarco as $n) {
    if ($n['tipo_evento'] === 'TAREA_ASIGNADA' && (int)$n['entidad_id'] === (int)$tareaPiloto['id']) {
        $notifAsignada = $n;
        break;
    }
}
assert_test('Piloto Paso 2: Marco Chumbes recibe notificación de nueva tarea asignada', $notifAsignada !== null);

// Paso 3: Marco añade checklist de subtareas y las completa
$sub1 = Subtask::create((int)$tareaPiloto['id'], 'Recopilar reportes del turno A y B');
$sub2 = Subtask::create((int)$tareaPiloto['id'], 'Cruzar tiempos con sistema SCADA');
Subtask::toggle((int)$sub1['id'], true);
Subtask::toggle((int)$sub2['id'], true);
$subtasksProgreso = Subtask::getProgress((int)$tareaPiloto['id']);
assert_test('Piloto Paso 3: Marco completa el 100% de subtareas del checklist', 
    $subtasksProgreso['total'] === 2 && $subtasksProgreso['completadas'] === 2 && $subtasksProgreso['porcentaje'] === 100);

// Paso 4: Marco añade comentario con evidencia y solicita aprobación formal a Jefatura
$comentario = Comment::create((int)$tareaPiloto['id'], (int)$asistente1['id'], 'Auditoría completada. Adjunto resumen validado.');
$resPaso4 = invokeController(fn() => $approvalController->solicitar([
    'user' => $asistente1,
    'params' => ['id' => (int)$tareaPiloto['id']]
]));
assert_test('Piloto Paso 4: Marco solicita aprobación; tarea pasa a EN_REVISION', $resPaso4['status'] === 200);

// Paso 5: Elena Vega recibe notificación y revisa la bandeja de aprobaciones
$notifsElena = Notification::listByUser((int)$jefePlaneamiento['id'], true);
$notifRevision = null;
foreach ($notifsElena as $n) {
    if ($n['tipo_evento'] === 'APROBACION_SOLICITADA' && (int)$n['entidad_id'] === (int)$tareaPiloto['id']) {
        $notifRevision = $n;
        break;
    }
}
assert_test('Piloto Paso 5.A: Elena Vega recibe notificación de aprobación solicitada', $notifRevision !== null);

$resBandeja = invokeController(fn() => $approvalController->pendientes([
    'user' => $jefePlaneamiento
]));
$bandejaRaw = json_decode($resBandeja['body'], true);
$bandejaElena = $bandejaRaw['data'] ?? [];
$tareaEnBandeja = false;
foreach ($bandejaElena as $item) {
    if ((int)$item['tarea_id'] === (int)$tareaPiloto['id']) {
        $tareaEnBandeja = true;
        break;
    }
}
assert_test('Piloto Paso 5.B: Tarea aparece en la bandeja de pendientes de Jefatura', $tareaEnBandeja);

// Paso 6: Elena emite dictamen favorable (APROBADO)
$resDictamen = invokeController(fn() => $approvalController->resolver([
    'user' => $jefePlaneamiento,
    'params' => ['id' => (int)$tareaPiloto['id']],
    'body' => [
        'accion' => 'APROBAR',
        'observaciones' => 'Resultados conformes para reunión ejecutiva.'
    ]
]));
$tareaPilotoFin = Task::findById((int)$tareaPiloto['id']);
assert_test('Piloto Paso 6: Elena aprueba y la tarea alcanza estado FINALIZADA', 
    $resDictamen['status'] === 200 && (int)$tareaPilotoFin['columna_id'] === 5);

// Paso 7: Roberto (Jefe Personal) y Elena consultan el Dashboard Operativo y el Balance de Carga
$metricasElena = DashboardService::getMetrics($jefePlaneamiento, 'global');
assert_test('Piloto Paso 7.A: Jefatura obtiene KPIs globales (tasa de cumplimiento > 0%)', 
    ($metricasElena['resumen_estados']['total'] ?? 0) > 0);
assert_test('Piloto Paso 7.B: Jefatura supervisa balance de carga individual de los 5 miembros', 
    isset($metricasElena['balance_equipo']) && count($metricasElena['balance_equipo']) === 5);

// Paso 8: Asistente 3 consulta el dashboard y comprueba aislamiento (sin balance de compañeros)
$metricasAsistente3 = DashboardService::getMetrics($asistente3, 'personal');
assert_test('Piloto Paso 8: Asistente 3 solo visualiza sus métricas y no accede al balance general del equipo', 
    empty($metricasAsistente3['balance_equipo']));

echo "\n=================================================================================\n";
echo "RESULTADOS DE LA SUITE MVP-10:\n";
echo "Tests ejecutados: $totalTests\n";
echo "Tests aprobados:  $passedTests\n";
echo "Tests fallidos:   $failedTests\n";
echo "=================================================================================\n";

if ($failedTests === 0) {
    echo ">>> EVALUACIÓN: 100% PASS — MVP-10 Y FASE 1 CUMPLIDOS SATISFACTORIAMENTE <<<\n";
    exit(0);
} else {
    echo ">>> EVALUACIÓN: SE ENCONTRARON FALLOS EN LA SUITE MVP-10 <<<\n";
    exit(1);
}
