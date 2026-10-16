<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Config\Database;
use App\Controllers\CommentController;
use App\Controllers\SubtaskController;
use App\Core\Middleware\AuthMiddleware;
use App\Core\Response;
use App\Core\Router;
use App\Core\UploadHandler;
use App\Models\Comment;
use App\Models\Session;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;

Response::$exitOnSend = false;

echo "=== INICIANDO SUITE DE PRUEBAS AUTOMATIZADAS: MVP-05 (SUBTAREAS, COMENTARIOS Y ADJUNTOS) ===\n\n";

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
// 2. Semillas: Columnas, Usuarios y Tareas
// -----------------------------------------------------------------------------
$pdo->exec("
    INSERT INTO tableros_columnas (id, nombre, orden, tipo_estado, color_hex, activo) VALUES
    (1, 'Pendiente', 1, 'PENDIENTE', '#64748b', 1),
    (2, 'En proceso', 2, 'EN_PROCESO', '#2563eb', 1),
    (3, 'Bloqueada', 3, 'BLOQUEADA', '#e11d48', 1),
    (4, 'En revisión', 4, 'EN_REVISION', '#d97706', 1),
    (5, 'Finalizada', 5, 'FINALIZADA', '#059669', 1);
");

$hashSeguro = password_hash('TestOnly-Planeamiento-2026!', PASSWORD_BCRYPT, ['cost' => 10]);
$now = gmdate('Y-m-d H:i:s');

$usuariosSeed = [
    [1, 'ASIST-01', 'Marco Chumbes', 'mchumbes@planeamiento.tal', $hashSeguro, User::ROL_ASISTENTE, 'Asistente 1', 1],
    [2, 'ASIST-02', 'Asistente Dos', 'asistente2@planeamiento.tal', $hashSeguro, User::ROL_ASISTENTE, 'Asistente 2', 1],
    [5, 'JEFE-PLA', 'Jefe Planeamiento', 'jefe.planeamiento@planeamiento.tal', $hashSeguro, User::ROL_JEFE_PLANEAMIENTO, 'Jefe', 1],
];

$stmt = $pdo->prepare("
    INSERT INTO usuarios (id, codigo, nombre, email, password_hash, rol, cargo, activo, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
foreach ($usuariosSeed as $u) {
    $stmt->execute([$u[0], $u[1], $u[2], $u[3], $u[4], $u[5], $u[6], $u[7], $now, $now]);
}

$tokenAsistente1 = Session::create(1, '127.0.0.1', 'CLI-Test');
$tokenAsistente2 = Session::create(2, '127.0.0.1', 'CLI-Test');
$tokenJefe = Session::create(5, '127.0.0.1', 'CLI-Test');

$userAsistente1 = User::formatPublic(User::findById(1));
$userAsistente2 = User::formatPublic(User::findById(2));
$userJefe = User::formatPublic(User::findById(5));

// Tarea 1 de Asistente 1, Tarea 2 de Asistente 2
$pdo->exec("
    INSERT INTO tareas (id, codigo, titulo, descripcion, tipo_tarea, columna_id, prioridad, creador_id, responsable_id, requiere_aprobacion, revisor_id, fecha_limite, fecha_finalizada, created_at, updated_at) VALUES
    (1, 'TAR-2026-0001', 'Conciliación de inventarios', 'Tarea de Asistente 1', 'AREA', 2, 'ALTA', 1, 1, 0, NULL, '2026-10-30', NULL, '{$now}', '{$now}'),
    (2, 'TAR-2026-0002', 'Informe mensual planta', 'Tarea de Asistente 2', 'AREA', 2, 'MEDIA', 5, 2, 1, 5, '2026-10-30', NULL, '{$now}', '{$now}');
");

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

$subtaskController = new SubtaskController();
$commentController = new CommentController();

// =============================================================================
// PRUEBAS DE SUBTAREAS (CHECKLIST)
// =============================================================================

// T01: Listar subtareas sin autenticación -> 401
$res = invokeCapture(function () use ($subtaskController) {
    $subtaskController->index(['params' => ['id' => '1']]);
});
testAssert("T01: GET /api/tareas/1/subtareas sin sesión retorna 401 Unauthorized", $res['status'] === 401);

// T02: Asistente 1 crea primera subtarea en su Tarea 1
$res = invokeCapture(function () use ($subtaskController, $userAsistente1) {
    $subtaskController->create([
        'user'   => $userAsistente1,
        'params' => ['id' => '1'],
        'body'   => ['titulo' => 'Revisar kardex almacén 1', 'orden' => 1]
    ]);
});
$sub1 = $res['json']['data']['subtarea'] ?? [];
testAssert(
    "T02: Asistente 1 crea subtarea 1 -> 201 Created con completada = false",
    $res['status'] === 201 && ($sub1['id'] ?? 0) === 1 && ($sub1['completada'] ?? true) === false
);

// T03: Asistente 1 crea segunda subtarea en su Tarea 1
$res = invokeCapture(function () use ($subtaskController, $userAsistente1) {
    $subtaskController->create([
        'user'   => $userAsistente1,
        'params' => ['id' => '1'],
        'body'   => ['titulo' => 'Exportar diferencias a Excel', 'orden' => 2]
    ]);
});
testAssert("T03: Asistente 1 crea subtarea 2 -> 201 Created", $res['status'] === 201);

// T04: Asistente 1 lista subtareas de Tarea 1 -> 2 subtareas
$res = invokeCapture(function () use ($subtaskController, $userAsistente1) {
    $subtaskController->index([
        'user'   => $userAsistente1,
        'params' => ['id' => '1']
    ]);
});
$subtareas = $res['json']['data']['subtareas'] ?? [];
testAssert(
    "T04: Asistente 1 lista subtareas de Tarea 1 -> 200 OK y 2 elementos",
    $res['status'] === 200 && count($subtareas) === 2
);

// T05: Seguridad Anti-IDOR: Asistente 1 intenta listar subtareas de Tarea 2 (de Asistente 2) -> 403 Forbidden
$res = invokeCapture(function () use ($subtaskController, $userAsistente1) {
    $subtaskController->index([
        'user'   => $userAsistente1,
        'params' => ['id' => '2']
    ]);
});
testAssert(
    "T05: [Seguridad Anti-IDOR] Asistente 1 intenta ver subtareas de Tarea 2 -> 403 Forbidden",
    $res['status'] === 403 && ($res['json']['error']['code'] ?? '') === 'ERR_FORBIDDEN_TASK_ACCESS'
);

// T06: Seguridad: Asistente 1 intenta agregar subtarea a Tarea 2 -> 403 Forbidden
$res = invokeCapture(function () use ($subtaskController, $userAsistente1) {
    $subtaskController->create([
        'user'   => $userAsistente1,
        'params' => ['id' => '2'],
        'body'   => ['titulo' => 'Intento ilegal']
    ]);
});
testAssert(
    "T06: [Seguridad] Asistente 1 intenta agregar subtarea a Tarea 2 -> 403 Forbidden",
    $res['status'] === 403 && ($res['json']['error']['code'] ?? '') === 'ERR_FORBIDDEN_ACTION'
);

// T07: Asistente 1 marca Subtarea 1 como completada
$res = invokeCapture(function () use ($subtaskController, $userAsistente1) {
    $subtaskController->update([
        'user'   => $userAsistente1,
        'params' => ['id' => '1'],
        'body'   => ['completada' => true]
    ]);
});
testAssert(
    "T07: Asistente 1 marca subtarea 1 como completada -> 200 OK y completada = true",
    $res['status'] === 200 && ($res['json']['data']['subtarea']['completada'] ?? false) === true
);

// T08: Seguridad: Asistente 2 intenta modificar la Subtarea 1 de Asistente 1 -> 403 Forbidden
$res = invokeCapture(function () use ($subtaskController, $userAsistente2) {
    $subtaskController->update([
        'user'   => $userAsistente2,
        'params' => ['id' => '1'],
        'body'   => ['completada' => false]
    ]);
});
testAssert(
    "T08: [Seguridad] Asistente 2 intenta modificar subtarea ajena -> 403 Forbidden",
    $res['status'] === 403
);

// T09: Asistente 1 elimina la Subtarea 2
$res = invokeCapture(function () use ($subtaskController, $userAsistente1) {
    $subtaskController->delete([
        'user'   => $userAsistente1,
        'params' => ['id' => '2']
    ]);
});
testAssert(
    "T09: Asistente 1 elimina subtarea 2 -> 200 OK",
    $res['status'] === 200
);

// T10: Comprobar que en Tarea 1 queda 1 sola subtarea
$res = invokeCapture(function () use ($subtaskController, $userAsistente1) {
    $subtaskController->index([
        'user'   => $userAsistente1,
        'params' => ['id' => '1']
    ]);
});
testAssert(
    "T10: Tarea 1 ahora posee exactamente 1 subtarea",
    count($res['json']['data']['subtareas'] ?? []) === 1
);

// =============================================================================
// PRUEBAS DE COMENTARIOS Y EVIDENCIAS ADJUNTAS SEGURAS
// =============================================================================

// T11: Asistente 1 agrega comentario en Tarea 1
$res = invokeCapture(function () use ($commentController, $userAsistente1) {
    $commentController->create([
        'user'   => $userAsistente1,
        'params' => ['id' => '1'],
        'body'   => ['comentario' => 'Se ha avanzado con el 50% de la conciliación física.', 'es_evidencia' => false]
    ]);
});
$com1 = $res['json']['data']['comentario'] ?? [];
testAssert(
    "T11: Asistente 1 registra comentario -> 201 Created y autor asociado",
    $res['status'] === 201 &&
    ($com1['usuario_id'] ?? 0) === 1 &&
    ($com1['usuario_primer_nombre'] ?? '') === 'Marco' &&
    ($com1['es_evidencia'] ?? true) === false
);

// T12: Seguridad: Asistente 1 intenta comentar en Tarea 2 -> 403 Forbidden
$res = invokeCapture(function () use ($commentController, $userAsistente1) {
    $commentController->create([
        'user'   => $userAsistente1,
        'params' => ['id' => '2'],
        'body'   => ['comentario' => 'Comentario no autorizado']
    ]);
});
testAssert(
    "T12: [Seguridad Anti-IDOR] Asistente 1 intenta comentar en Tarea 2 -> 403 Forbidden",
    $res['status'] === 403
);

// T13: Jefatura comenta en Tarea 2 oficial del área
$res = invokeCapture(function () use ($commentController, $userJefe) {
    $commentController->create([
        'user'   => $userJefe,
        'params' => ['id' => '2'],
        'body'   => ['comentario' => 'Por favor priorizar el área de molienda.', 'es_evidencia' => false]
    ]);
});
testAssert(
    "T13: [Supervisión Jefatura] Jefe comenta en Tarea 2 del área -> 201 Created",
    $res['status'] === 201 && ($res['json']['data']['comentario']['usuario_id'] ?? 0) === 5
);

// T14: Asistente 1 intenta listar comentarios de Tarea 2 -> 403 Forbidden
$res = invokeCapture(function () use ($commentController, $userAsistente1) {
    $commentController->index([
        'user'   => $userAsistente1,
        'params' => ['id' => '2']
    ]);
});
testAssert(
    "T14: [Seguridad Anti-IDOR] Asistente 1 intenta listar comentarios de Tarea 2 -> 403 Forbidden",
    $res['status'] === 403
);

// -----------------------------------------------------------------------------
// Pruebas de Carga de Archivos Adjuntos Seguros
// -----------------------------------------------------------------------------

// Crear archivo temporal simulado seguro (PDF)
$tempPdf = tempnam(sys_get_temp_dir(), 'test_pdf_');
file_put_contents($tempPdf, "%PDF-1.4 Informe de Conciliacion de Mermas");

// T15: Asistente 1 sube comentario con evidencia adjunta válida (PDF)
$res = invokeCapture(function () use ($commentController, $userAsistente1, $tempPdf) {
    $commentController->create([
        'user'   => $userAsistente1,
        'params' => ['id' => '1'],
        'body'   => [
            'comentario'   => 'Adjunto informe final de conciliación firmado.',
            'es_evidencia' => true
        ],
        'files'  => [
            'archivo' => [
                'name'     => 'informe_conciliacion_2026.pdf',
                'type'     => 'application/pdf',
                'tmp_name' => $tempPdf,
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($tempPdf)
            ]
        ]
    ]);
});
$comEvidencia = $res['json']['data']['comentario'] ?? [];
testAssert(
    "T15: Asistente 1 sube evidencia adjunta (PDF) -> 201 Created con metadatos seguros de archivo",
    $res['status'] === 201 &&
    ($comEvidencia['es_evidencia'] ?? false) === true &&
    isset($comEvidencia['adjunto']['nombre_original']) &&
    $comEvidencia['adjunto']['nombre_original'] === 'informe_conciliacion_2026.pdf'
);

// T15b: una evidencia puede enviarse sin texto adicional.
$tempSoloArchivo = tempnam(sys_get_temp_dir(), 'test_file_');
file_put_contents($tempSoloArchivo, "Archivo de evidencia sin comentario");
$res = invokeCapture(function () use ($commentController, $userAsistente1, $tempSoloArchivo) {
    $commentController->create([
        'user' => $userAsistente1,
        'params' => ['id' => '1'],
        'body' => [],
        'files' => ['archivo' => [
            'name' => 'evidencia.txt', 'tmp_name' => $tempSoloArchivo,
            'error' => UPLOAD_ERR_OK, 'size' => filesize($tempSoloArchivo)
        ]]
    ]);
});
testAssert(
    "T15b: Asistente envía evidencia sin texto -> 201 Created y adjunto descargable",
    $res['status'] === 201 && !empty($res['json']['data']['comentario']['adjunto']['descarga_url'])
);

// T16 (Seguridad negativa): Intento de subir archivo con extensión prohibida (.php)
$tempMalicioso = tempnam(sys_get_temp_dir(), 'test_mal_');
file_put_contents($tempMalicioso, "<?php echo 'hacked'; ?>");
$res = invokeCapture(function () use ($commentController, $userAsistente1, $tempMalicioso) {
    $commentController->create([
        'user'   => $userAsistente1,
        'params' => ['id' => '1'],
        'body'   => ['comentario' => 'Intento de subir script php'],
        'files'  => [
            'archivo' => [
                'name'     => 'exploit.php',
                'type'     => 'application/x-php',
                'tmp_name' => $tempMalicioso,
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($tempMalicioso)
            ]
        ]
    ]);
});
testAssert(
    "T16: [Seguridad Uploads] Intento de subir archivo .php es bloqueado con 422 (ERR_UPLOAD_FAILED)",
    $res['status'] === 422 && ($res['json']['error']['code'] ?? '') === 'ERR_UPLOAD_FAILED'
);

// T17 (Seguridad negativa): Intento de subir archivo que excede 10 MB
$res = invokeCapture(function () use ($commentController, $userAsistente1, $tempPdf) {
    $commentController->create([
        'user'   => $userAsistente1,
        'params' => ['id' => '1'],
        'body'   => ['comentario' => 'Archivo gigante'],
        'files'  => [
            'archivo' => [
                'name'     => 'archivo_gigante.pdf',
                'type'     => 'application/pdf',
                'tmp_name' => $tempPdf,
                'error'    => UPLOAD_ERR_OK,
                'size'     => UploadHandler::MAX_FILE_SIZE_BYTES + 1024 // Excede 10MB
            ]
        ]
    ]);
});
testAssert(
    "T17: [Seguridad Uploads] Archivo mayor a 10 MB es rechazado con 422",
    $res['status'] === 422
);

// T18: Descarga segura: Asistente 1 descarga la evidencia que subió
$evidenciaCommentId = (string)($comEvidencia['id'] ?? 0);
$res = invokeCapture(function () use ($commentController, $userAsistente1, $evidenciaCommentId) {
    $commentController->download([
        'user'   => $userAsistente1,
        'params' => ['id' => $evidenciaCommentId]
    ]);
});
testAssert(
    "T18: Asistente 1 descarga evidencia de su tarea -> Contenido del PDF retornado con éxito",
    str_contains($res['raw'], "%PDF-1.4 Informe de Conciliacion")
);

// T19: Seguridad Anti-IDOR en descarga: Asistente 2 intenta descargar la evidencia de Asistente 1 -> 403 Forbidden
$res = invokeCapture(function () use ($commentController, $userAsistente2, $evidenciaCommentId) {
    $commentController->download([
        'user'   => $userAsistente2,
        'params' => ['id' => $evidenciaCommentId]
    ]);
});
testAssert(
    "T19: [Seguridad Anti-IDOR] Asistente 2 intenta descargar evidencia ajena -> 403 Forbidden (ERR_FORBIDDEN_FILE_ACCESS)",
    $res['status'] === 403 && ($res['json']['error']['code'] ?? '') === 'ERR_FORBIDDEN_FILE_ACCESS'
);

// T20: Supervisión Jefatura: Jefe puede descargar la evidencia de la tarea oficial de Asistente 1
$res = invokeCapture(function () use ($commentController, $userJefe, $evidenciaCommentId) {
    $commentController->download([
        'user'   => $userJefe,
        'params' => ['id' => $evidenciaCommentId]
    ]);
});
testAssert(
    "T20: [Supervisión Jefatura] Jefe descarga evidencia oficial de tarea del área -> Autorizado con éxito",
    str_contains($res['raw'], "%PDF-1.4 Informe de Conciliacion")
);

// =============================================================================
// PRUEBAS DE ENRUTAMIENTO HTTP Y DISPATCH DEL ROUTER (INTEGRACIÓN)
// =============================================================================

$router = new Router();
$router->get('/api/tareas/{id}/subtareas', [SubtaskController::class, 'index'], [AuthMiddleware::class]);
$router->get('/api/tareas/{id}/comentarios', [CommentController::class, 'index'], [AuthMiddleware::class]);

// T21: Dispatch GET /api/tareas/1/subtareas con sesión activa de Asistente 1
$_COOKIE['auth_token'] = $tokenAsistente1;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/tareas/1/subtareas';

$res = invokeCapture(function () use ($router) {
    $router->dispatch('GET', '/api/tareas/1/subtareas');
});
testAssert(
    "T21: Router dispatch GET /api/tareas/1/subtareas -> 200 OK y lista retornada",
    $res['status'] === 200 && is_array($res['json']['data']['subtareas'] ?? null)
);

// T22: Dispatch GET /api/tareas/1/comentarios con sesión activa de Asistente 1
$res = invokeCapture(function () use ($router) {
    $router->dispatch('GET', '/api/tareas/1/comentarios');
});
testAssert(
    "T22: Router dispatch GET /api/tareas/1/comentarios -> 200 OK y 2 comentarios (uno con evidencia)",
    $res['status'] === 200 && count($res['json']['data']['comentarios'] ?? []) === 3
);

// Limpiar archivos temporales de prueba
@unlink($tempPdf);
@unlink($tempMalicioso);

// -----------------------------------------------------------------------------
// Resumen Final
// -----------------------------------------------------------------------------
echo "\n=== RESUMEN DE PRUEBAS MVP-05 ===\n";
echo "Total ejecutadas: {$totalTests}\n";
echo "Aprobadas:        {$passedTests}\n";
echo "Fallidas:         " . ($totalTests - $passedTests) . "\n";

if ($passedTests === $totalTests) {
    echo "\n>>> RESULTADO: TODAS LAS PRUEBAS DE MVP-05 PASARON EXITOSAMENTE (100% PASS) <<<\n";
    exit(0);
} else {
    echo "\n>>> RESULTADO: EXISTEN PRUEBAS FALLIDAS EN MVP-05 <<<\n";
    exit(1);
}
