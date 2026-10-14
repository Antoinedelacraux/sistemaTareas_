<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Config\Database;
use App\Controllers\AuditController;
use App\Controllers\DashboardController;
use App\Core\Response;
use App\Models\Approval;
use App\Models\Audit;
use App\Models\BoardColumn;
use App\Models\Comment;
use App\Models\Session;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\User;
use App\Services\DashboardService;

Response::$exitOnSend = false;

echo "=== INICIANDO SUITE DE PRUEBAS AUTOMATIZADAS: MVP-09 (HISTORIAL, AUDITORÍA Y DASHBOARD) ===\n\n";

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
    (2, 'ASIST-02', 'Asistente Dos', 'asistente2@planeamiento.tal', '{$passwordHash}', 'ASISTENTE', 'Asistente 2', 1, '{$now}', '{$now}'),
    (3, 'JEFE-01', 'Elena Vega', 'jefe.planeamiento@planeamiento.tal', '{$passwordHash}', 'JEFE_PLANEAMIENTO', 'Jefe Planeamiento', 1, '{$now}', '{$now}');
");

$asist1Token = Session::create(1);
$asist2Token = Session::create(2);
$jefeToken   = Session::create(3);

$userAsist1 = User::findById(1);
$userAsist2 = User::findById(2);
$userJefe   = User::findById(3);

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
// BLOQUE 1: Registro y Consulta de Historial Inmutable de Auditoría (RF-18, RN-13)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 1: HISTORIAL Y AUDITORÍA INMUTABLE DE TAREAS (RF-18, RN-13) ---\n";

$colPendiente = BoardColumn::findByTipoEstado('PENDIENTE');
$colEnProceso = BoardColumn::findByTipoEstado('EN_PROCESO');

// Crear tarea por el Jefe asignada a Asistente 1
$t1 = Task::create([
    'titulo'         => 'Tarea Auditoría Test',
    'descripcion'    => 'Verificando trazabilidad',
    'columna_id'     => $colPendiente['id'],
    'tipo_tarea'     => 'AREA',
    'prioridad'      => 'ALTA',
    'responsable_id' => $userAsist1['id']
], $userJefe);

$historial1 = Audit::getByTask((int)$t1['id']);
assertTest(count($historial1) >= 1, "Auditoría registró automáticamente la creación de la tarea");
assertTest($historial1[0]['accion'] === 'CREAR', "Primer evento registrado es 'CREAR'");
assertTest($historial1[0]['usuario_nombre'] === 'Elena Vega', "Actor registrado en auditoría es la Jefa creadora");

// Asistente 1 mueve tarea a EN_PROCESO
Task::transition((int)$t1['id'], (int)$colEnProceso['id'], [], $userAsist1);

// Asistente 1 agrega un comentario
Comment::create((int)$t1['id'], (int)$userAsist1['id'], 'Iniciando análisis preliminar.');

// Jefa reasigna la tarea a Asistente 2
Task::update((int)$t1['id'], [
    'responsable_id' => $userAsist2['id']
], $userJefe);

$historialCompleto = Audit::getByTask((int)$t1['id']);
assertTest(count($historialCompleto) >= 4, "Historial contiene todos los eventos secuenciales de la tarea (crear, transicionar, comentar, reasignar)");

$acciones = array_column($historialCompleto, 'accion');
assertTest(in_array('TRANSICION', $acciones, true), "Evento de transición de columna registrado en auditoría");
assertTest(in_array('COMENTARIO_AGREGADO', $acciones, true), "Evento de comentario registrado en auditoría");
assertTest(in_array('REASIGNAR', $acciones, true), "Evento de reasignación registrado en auditoría");

// -----------------------------------------------------------------------------
// BLOQUE 2: Seguridad y Control de Acceso Anti-IDOR en Historial (RN-02)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 2: SEGURIDAD Y CONTROL DE ACCESO ANTI-IDOR EN HISTORIAL ---\n";

// Crear tarea personal de Asistente 2
$tPersonal2 = Task::create([
    'titulo'         => 'Notas Personales Asistente 2',
    'descripcion'    => 'Privada',
    'columna_id'     => $colPendiente['id'],
    'tipo_tarea'     => 'PERSONAL',
    'prioridad'      => 'BAJA',
    'responsable_id' => $userAsist2['id']
], $userAsist2);

$auditCtrl = new AuditController();

// 1. Asistente 1 intenta consultar historial de la tarea personal de Asistente 2 (Anti-IDOR)
ob_start();
$auditCtrl->taskHistory(['user' => $userAsist1, 'params' => ['id' => $tPersonal2['id']]]);
$outIdor = ob_get_clean();
$jsonIdor = json_decode($outIdor, true);

assertTest(Response::$lastStatusCode === 403, "Anti-IDOR: Asistente 1 recibe 403 Forbidden al consultar tarea ajena");
assertTest(($jsonIdor['error']['code'] ?? '') === 'ERR_FORBIDDEN_ACTION', "Código de error es ERR_FORBIDDEN_ACTION");

// 2. Asistente 2 consulta su propia tarea personal -> 200 OK
ob_start();
$auditCtrl->taskHistory(['user' => $userAsist2, 'params' => ['id' => $tPersonal2['id']]]);
$outOwner = ob_get_clean();
$jsonOwner = json_decode($outOwner, true);

assertTest(Response::$lastStatusCode === 200, "Propietario consulta con éxito el historial de su tarea (200 OK)");
assertTest(isset($jsonOwner['data']['historial']), "Respuesta incluye array de historial");

// 3. Jefa consulta historial de tarea oficial t1 -> 200 OK
ob_start();
$auditCtrl->taskHistory(['user' => $userJefe, 'params' => ['id' => $t1['id']]]);
$outJefe = ob_get_clean();
$jsonJefe = json_decode($outJefe, true);

assertTest(Response::$lastStatusCode === 200, "Jefa supervisa con éxito el historial de tarea oficial del área (200 OK)");

// -----------------------------------------------------------------------------
// BLOQUE 3: Auditoría General del Sistema (/api/auditoria)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 3: AUDITORÍA GENERAL DEL SISTEMA (/api/auditoria) ---\n";

// Jefa consulta auditoría global
ob_start();
$auditCtrl->index(['user' => $userJefe, 'query' => []]);
$outAuditGlobal = ob_get_clean();
$jsonAuditGlobal = json_decode($outAuditGlobal, true);

assertTest(Response::$lastStatusCode === 200, "GET /api/auditoria para Jefatura retorna 200 OK");
assertTest($jsonAuditGlobal['data']['es_jefe'] === true, "Respuesta identifica rol privilegiado es_jefe=true");
assertTest(count($jsonAuditGlobal['data']['eventos']) >= 4, "Jefatura tiene visibilidad de todos los eventos del sistema");

// Asistente 1 consulta auditoría (debe filtrarse solo a sus propios eventos)
ob_start();
$auditCtrl->index(['user' => $userAsist1, 'query' => []]);
$outAuditAsist = ob_get_clean();
$jsonAuditAsist = json_decode($outAuditAsist, true);

assertTest(Response::$lastStatusCode === 200, "GET /api/auditoria para Asistente retorna 200 OK");
assertTest($jsonAuditAsist['data']['es_jefe'] === false, "Respuesta identifica rol no privilegiado es_jefe=false");

// Validar que todos los eventos devueltos al asistente correspondan únicamente a su usuario
$eventosAsist = $jsonAuditAsist['data']['eventos'];
$todosSonDeAsist1 = true;
foreach ($eventosAsist as $ev) {
    if ((int)$ev['usuario_id'] !== (int)$userAsist1['id']) {
        $todosSonDeAsist1 = false;
        break;
    }
}
assertTest($todosSonDeAsist1, "Filtro de seguridad: Asistente 1 solo recibe eventos originados por él mismo");

// -----------------------------------------------------------------------------
// BLOQUE 4: Métricas Operativas y KPIs en DashboardService (RF-19)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 4: MÉTRICAS Y KPIS DE DASHBOARD (RF-19) ---\n";

// Sembrar tareas adicionales con diferentes estados y fechas límite
$colBloqueada = BoardColumn::findByTipoEstado('BLOQUEADA');
$colEnRevision = BoardColumn::findByTipoEstado('EN_REVISION');
$colFinalizada = BoardColumn::findByTipoEstado('FINALIZADA');

// Tarea bloqueada
Task::create([
    'titulo'         => 'Tarea Bloqueada Test',
    'columna_id'     => $colBloqueada['id'],
    'tipo_tarea'     => 'AREA',
    'prioridad'      => 'ALTA',
    'responsable_id' => $userAsist1['id']
], $userJefe);

// Tarea en revisión
$tRev = Task::create([
    'titulo'         => 'Tarea en Revisión Test',
    'columna_id'     => $colEnRevision['id'],
    'tipo_tarea'     => 'AREA',
    'prioridad'      => 'MEDIA',
    'responsable_id' => $userAsist2['id']
], $userJefe);

// Tarea finalizada
$tFin = Task::create([
    'titulo'         => 'Tarea Finalizada Test',
    'columna_id'     => $colFinalizada['id'],
    'tipo_tarea'     => 'AREA',
    'prioridad'      => 'BAJA',
    'responsable_id' => $userAsist1['id']
], $userJefe);

// Tarea vencida
$fechaPasada = gmdate('Y-m-d H:i:s', time() - 86400); // Ayer
Task::create([
    'titulo'         => 'Tarea Vencida Test',
    'columna_id'     => $colEnProceso['id'],
    'tipo_tarea'     => 'AREA',
    'prioridad'      => 'ALTA',
    'responsable_id' => $userAsist1['id'],
    'fecha_limite'   => $fechaPasada
], $userJefe);

// Consultar métricas como Jefatura para ámbito del área
$metricasJefe = DashboardService::getMetrics($userJefe, 'area');

assertTest(isset($metricasJefe['resumen_estados']), "Métricas incluyen resumen de estados");
assertTest($metricasJefe['resumen_estados']['bloqueadas'] >= 1, "Métricas contabilizan tareas bloqueadas");
assertTest($metricasJefe['resumen_estados']['en_revision'] >= 1, "Métricas contabilizan tareas en revisión");
assertTest($metricasJefe['resumen_estados']['finalizadas'] >= 1, "Métricas contabilizan tareas finalizadas");
assertTest($metricasJefe['resumen_estados']['tasa_cumplimiento'] > 0, "Tasa de cumplimiento calculada en porcentaje");
assertTest($metricasJefe['alertas_plazos']['vencidas'] >= 1, "Métricas detectan alerta de tareas vencidas");
assertTest(!empty($metricasJefe['balance_equipo']), "Jefatura recibe balance de carga del equipo de trabajo");

// Validar estructura de balance de equipo
$primeroEquipo = $metricasJefe['balance_equipo'][0];
assertTest(isset($primeroEquipo['usuario_id']) && isset($primeroEquipo['total_asignadas']), "Balance de equipo incluye usuario y total de asignadas");
assertTest(isset($primeroEquipo['tasa_cumplimiento']), "Balance de equipo calcula tasa de cumplimiento individual");

// -----------------------------------------------------------------------------
// BLOQUE 5: Métricas para Asistente (Alcance Personal vs Oficial Asignado)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 5: MÉTRICAS PARA ASISTENTE (SEGURIDAD Y ALCANCE) ---\n";

$metricasAsist1 = DashboardService::getMetrics($userAsist1, 'area');

assertTest($metricasAsist1['es_jefe'] === false, "Identifica usuario no jefe");
assertTest(empty($metricasAsist1['balance_equipo']), "Asistente NO tiene acceso al balance de carga global del equipo");

// Las tareas contadas para Asistente 1 no deben incluir las de Asistente 2
$totalAsist1 = $metricasAsist1['resumen_estados']['total'];
$totalJefe   = $metricasJefe['resumen_estados']['total'];
assertTest($totalAsist1 < $totalJefe, "Asistente solo contabiliza sus propias tareas asignadas ({$totalAsist1} < {$totalJefe})");

// -----------------------------------------------------------------------------
// BLOQUE 6: Endpoint HTTP GET /api/dashboard/metricas
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 6: ENDPOINT HTTP GET /api/dashboard/metricas ---\n";

$dashCtrl = new DashboardController();

ob_start();
$dashCtrl->metrics(['user' => $userJefe, 'query' => ['scope' => 'area']]);
$outDash = ob_get_clean();
$jsonDash = json_decode($outDash, true);

assertTest(Response::$lastStatusCode === 200, "GET /api/dashboard/metricas retorna 200 OK");
assertTest($jsonDash['success'] === true, "Envelope success es true");
assertTest(isset($jsonDash['data']['resumen_estados']['tasa_cumplimiento']), "Datos devuelven tasa de cumplimiento");
assertTest(isset($jsonDash['data']['alertas_plazos']['vencidas']), "Datos devuelven conteo de alertas de plazos");

// -----------------------------------------------------------------------------
// Resumen Final
// -----------------------------------------------------------------------------
echo "\n=======================================================\n";
echo "RESULTADO SUITE MVP-09: {$testsPassed} de {$testsCount} tests aprobados (" . round(($testsPassed / $testsCount) * 100) . "%)\n";
echo "=======================================================\n\n";

if ($testsPassed === $testsCount) {
    echo ">>> TODOS LOS TESTS DE HISTORIAL, AUDITORÍA Y DASHBOARD (MVP-09) PASARON EXITOSAMENTE <<<\n\n";
    exit(0);
} else {
    echo ">>> EXISTEN TESTS FALLIDOS EN MVP-09 <<<\n\n";
    exit(1);
}
