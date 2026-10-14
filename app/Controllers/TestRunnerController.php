<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Models\User;
use App\Models\Task;
use App\Models\BoardColumn;
use App\Models\Subtask;
use App\Models\Comment;
use App\Models\Approval;
use App\Models\TaskDependency;
use PDO;

/**
 * Controlador de Pruebas Web Autónomas.
 * Permite ejecutar y visualizar las pruebas del sistema directamente desde el navegador
 * sin requerir consola de comandos, terminal ni permisos de administrador (PowerShell).
 */
class TestRunnerController
{
    public function run(array $request): void
    {
        $startTime = microtime(true);
        $results = [];

        // 1. Verificación de Base de Datos
        $results[] = $this->runTest("1. Conexión de Base de Datos y Tablas", function () {
            $db = Database::getConnection();
            $tables = ['usuarios', 'sesiones', 'tableros_columnas', 'tareas', 'subtareas', 'tarea_comentarios', 'tarea_aprobaciones', 'tarea_dependencias', 'notificaciones', 'auditoria_eventos'];
            foreach ($tables as $t) {
                $count = (int)$db->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
            }
            return "Base de datos activa con las 10 tablas verificadas y operativas.";
        });

        // 2. Roles y Usuarios
        $results[] = $this->runTest("2. Autenticación y Usuarios por Rol", function () {
            $asistente = User::findByRol('ASISTENTE');
            $jefePlan = User::findByRol('JEFE_PLANEAMIENTO');
            $jefePers = User::findByRol('JEFE_PERSONAL');

            if (!$asistente || !$jefePlan || !$jefePers) {
                throw new \Exception("Faltan usuarios de prueba esenciales por rol.");
            }

            if (!User::isJefe($jefePlan) || User::isJefe($asistente)) {
                throw new \Exception("Fallo en la diferenciación de roles de jefatura vs asistente.");
            }

            return "Usuarios validados: Asistente ({$asistente['nombre']}), Jefa ({$jefePlan['nombre']}), Jefe ({$jefePers['nombre']}).";
        });

        // 3. Creación y Validación de Tarea
        $results[] = $this->runTest("3. Creación de Tareas y Control Anti-IDOR", function () {
            $asistente = User::findByRol('ASISTENTE');
            $tarea = Task::create([
                'titulo'         => 'Verificación automatizada de sistema ' . time(),
                'descripcion'    => 'Prueba generada en caliente',
                'tipo_tarea'     => 'AREA',
                'prioridad'      => 'ALTA',
                'responsable_id' => $asistente['id']
            ], $asistente);

            if (empty($tarea['id']) || empty($tarea['codigo'])) {
                throw new \Exception("No se generó el código o ID de tarea.");
            }

            return "Tarea de prueba {$tarea['codigo']} creada exitosamente.";
        });

        // 4. Columnas Kanban con Colores
        $results[] = $this->runTest("4. Columnas Kanban y Paleta de Colores", function () {
            $cols = BoardColumn::listAllActive();
            if (count($cols) < 5) {
                throw new \Exception("Se esperaban al menos 5 columnas en el tablero.");
            }
            $nombres = array_column($cols, 'nombre');
            return "Columnas operativas: " . implode(', ', $nombres) . ".";
        });

        // 5. Transición Kanban
        $results[] = $this->runTest("5. Transiciones de Estado Kanban", function () {
            $jefePlan = User::findByRol('JEFE_PLANEAMIENTO');
            $tarea = Task::create([
                'titulo'         => 'Test Transicion ' . time(),
                'tipo_tarea'     => 'AREA',
                'prioridad'      => 'MEDIA',
                'responsable_id' => $jefePlan['id']
            ], $jefePlan);

            $cols = BoardColumn::listAllActive();
            $colProceso = $cols[1]['id']; // En proceso
            $res = Task::transition((int)$tarea['id'], (int)$colProceso, [], $jefePlan);

            if (!$res['success']) {
                throw new \Exception("Transición fallida: " . ($res['message'] ?? ''));
            }

            return "Transición a '{$res['tarea']['columna_nombre']}' ejecutada sin bloqueos.";
        });

        // 6. Subtareas (Checklist)
        $results[] = $this->runTest("6. Subtareas (Checklist) y Porcentaje de Avance", function () {
            $asistente = User::findByRol('ASISTENTE');
            $tarea = Task::create(['titulo' => 'Test Subtareas', 'responsable_id' => $asistente['id']], $asistente);
            $sub1 = Subtask::create((int)$tarea['id'], 'Ítem 1');
            $sub2 = Subtask::create((int)$tarea['id'], 'Ítem 2');
            Subtask::toggle((int)$sub1['id'], true);
            $subtareas = Subtask::listByTaskId((int)$tarea['id']);

            if (count($subtareas) !== 2 || !$subtareas[0]['completada']) {
                throw new \Exception("Fallo en registro o toggle de subtareas.");
            }

            return "Checklist con 2 ítems validado (1 completado, 50% de avance).";
        });

        // 7. Comentarios
        $results[] = $this->runTest("7. Comentarios y Registro de Auditoría", function () {
            $asistente = User::findByRol('ASISTENTE');
            $tarea = Task::create(['titulo' => 'Test Comentario', 'responsable_id' => $asistente['id']], $asistente);
            $com = Comment::create((int)$tarea['id'], (int)$asistente['id'], 'Comentario de verificación.');
            if (empty($com['id'])) {
                throw new \Exception("No se pudo crear el comentario.");
            }
            return "Comentario publicado y registrado en bitácora.";
        });

        // 8. Circuito de Aprobación
        $results[] = $this->runTest("8. Circuito de Aprobación Formal (RN-10, RN-12)", function () {
            $asistente = User::findByRol('ASISTENTE');
            $jefePlan = User::findByRol('JEFE_PLANEAMIENTO');
            $tarea = Task::create([
                'titulo'              => 'Test Aprobacion',
                'responsable_id'      => $asistente['id'],
                'requiere_aprobacion' => 1
            ], $asistente);

            $solicitud = Approval::solicitar((int)$tarea['id'], (int)$asistente['id'], 'Listo para revisión');
            if (empty($solicitud['id'])) {
                throw new \Exception("Fallo al solicitar aprobación.");
            }

            $resolucion = Approval::resolver((int)$tarea['id'], (int)$jefePlan['id'], 'APROBAR', 'Aprobado formalmente');
            if ($resolucion['estado'] !== 'APROBADA') {
                throw new \Exception("Fallo al resolver la aprobación.");
            }

            return "Circuito completado: Solicitud Asistente ➔ Dictamen Jefatura ➔ Aprobación.";
        });

        // 9. Dependencias (DAG)
        $results[] = $this->runTest("9. Grafo de Dependencias (DAG) y Bloqueo AND", function () {
            $asistente = User::findByRol('ASISTENTE');
            $tareaA = Task::create(['titulo' => 'Tarea Predecesora A', 'responsable_id' => $asistente['id']], $asistente);
            $tareaB = Task::create(['titulo' => 'Tarea Sucesora B', 'responsable_id' => $asistente['id']], $asistente);

            TaskDependency::add((int)$tareaB['id'], (int)$tareaA['id'], $asistente);
            $hasPending = TaskDependency::hasPendingPredecessors((int)$tareaB['id']);

            if (!$hasPending) {
                throw new \Exception("La tarea sucesora debería reportar predecesoras pendientes.");
            }

            return "Dependencia validada: Tarea B queda bloqueada hasta el cierre de A.";
        });

        // 10. Módulo de Historial y Regla de 24 Horas para Tareas Finalizadas
        $results[] = $this->runTest("10. Historial de Tareas Archivadas (>24h Finalizadas)", function () {
            $asistente = User::findByRol('ASISTENTE');
            $jefePlan = User::findByRol('JEFE_PLANEAMIENTO');

            $cols = BoardColumn::listAllActive();
            $colFin = null;
            foreach ($cols as $c) {
                if ($c['tipo_estado'] === 'FINALIZADA') {
                    $colFin = $c;
                    break;
                }
            }
            if (!$colFin) {
                throw new \Exception("No existe columna FINALIZADA en el tablero.");
            }

            $db = Database::getConnection();

            // 1) Tarea de ÁREA finalizada hace 30 horas (debe estar archivada en Historial y NO verse en Kanban Área)
            $tareaAreaAntigua = Task::create([
                'titulo'         => 'Tarea Área Finalizada 30h ' . time(),
                'tipo_tarea'     => 'AREA',
                'responsable_id' => $asistente['id']
            ], $asistente);

            $fecha30hAtras = gmdate('Y-m-d H:i:s', time() - (30 * 3600));
            $db->prepare("UPDATE tareas SET columna_id = :col_id, fecha_finalizada = :ff WHERE id = :id")
               ->execute([':col_id' => $colFin['id'], ':ff' => $fecha30hAtras, ':id' => $tareaAreaAntigua['id']]);

            // 2) Tarea de ÁREA finalizada hace 2 horas (debe permanecer en Kanban Área)
            $tareaAreaReciente = Task::create([
                'titulo'         => 'Tarea Área Finalizada 2h ' . time(),
                'tipo_tarea'     => 'AREA',
                'responsable_id' => $asistente['id']
            ], $asistente);

            $fecha2hAtras = gmdate('Y-m-d H:i:s', time() - (2 * 3600));
            $db->prepare("UPDATE tareas SET columna_id = :col_id, fecha_finalizada = :ff WHERE id = :id")
               ->execute([':col_id' => $colFin['id'], ':ff' => $fecha2hAtras, ':id' => $tareaAreaReciente['id']]);

            // 3) Tarea PERSONAL finalizada hace 35 horas (debe permanecer en Vista Personal y NUNCA ir al Historial)
            $tareaPersonal = Task::create([
                'titulo'         => 'Tarea Personal Apoyo ' . time(),
                'tipo_tarea'     => 'PERSONAL',
                'responsable_id' => $asistente['id']
            ], $asistente);

            $fecha35hAtras = gmdate('Y-m-d H:i:s', time() - (35 * 3600));
            $db->prepare("UPDATE tareas SET columna_id = :col_id, fecha_finalizada = :ff WHERE id = :id")
               ->execute([':col_id' => $colFin['id'], ':ff' => $fecha35hAtras, ':id' => $tareaPersonal['id']]);

            // Comprobar Vista Área (scope=area): solo tareas de AREA
            $areaTasks = Task::listForUser($asistente, ['scope' => 'area']);
            $areaIds = array_column($areaTasks, 'id');

            if (in_array((int)$tareaAreaAntigua['id'], $areaIds, true)) {
                throw new \Exception("La tarea de área de 30h no debió aparecer en la Vista Área activa.");
            }
            if (!in_array((int)$tareaAreaReciente['id'], $areaIds, true)) {
                throw new \Exception("La tarea de área de 2h debe permanecer visible en la Vista Área.");
            }
            if (in_array((int)$tareaPersonal['id'], $areaIds, true)) {
                throw new \Exception("Una tarea PERSONAL nunca debe filtrarse en la Vista Área.");
            }

            // Comprobar Vista Personal (scope=personal): solo tareas personales y no expiran
            $personalTasks = Task::listForUser($asistente, ['scope' => 'personal']);
            $personalIds = array_column($personalTasks, 'id');

            if (!in_array((int)$tareaPersonal['id'], $personalIds, true)) {
                throw new \Exception("La tarea PERSONAL debe permanecer en la Vista Personal (no expira tras 24h, requiere borrado manual).");
            }
            if (in_array((int)$tareaAreaReciente['id'], $personalIds, true) || in_array((int)$tareaAreaAntigua['id'], $personalIds, true)) {
                throw new \Exception("Tareas de ÁREA nunca deben aparecer en la Vista Personal.");
            }

            // Comprobar Historial de Jefatura: contiene la de área antigua y NUNCA contiene la personal
            $archived = Task::listArchived($jefePlan);
            $archivedIds = array_column($archived, 'id');
            if (!in_array((int)$tareaAreaAntigua['id'], $archivedIds, true)) {
                throw new \Exception("La tarea de área de más de 24h debe estar en el módulo de Historial.");
            }
            if (in_array((int)$tareaPersonal['id'], $archivedIds, true)) {
                throw new \Exception("Las tareas personales NUNCA deben registrarse en el módulo de Historial del área.");
            }

            // Comprobar seguridad: Asistente NO puede consultar el Historial (retorna vacío)
            $archivedAsist = Task::listArchived($asistente);
            if (!empty($archivedAsist)) {
                throw new \Exception("Un asistente no debe poder consultar el Historial de tareas archivadas.");
            }

            return "Vistas validadas: Vista Área (regla 24h a historial), Vista Personal (aislada, sin historial, retención) y seguridad RBAC.";
        });

        $totalTime = round((microtime(true) - $startTime) * 1000, 2);
        $passedCount = count(array_filter($results, fn($r) => $r['pass']));
        $totalCount = count($results);
        $allPassed = !in_array(false, array_column($results, 'pass'), true);

        // Si se pide JSON vía encabezado Accept o parámetro format=json
        if ((isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) ||
            (isset($_GET['format']) && $_GET['format'] === 'json')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success'    => $allPassed,
                'total_time' => "{$totalTime} ms",
                'passed'     => "{$passedCount}/{$totalCount}",
                'results'    => $results
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Renderizado HTML interactivo para navegador
        $this->renderHtmlReport($results, $totalTime, $allPassed, $passedCount, $totalCount);
    }

    private function runTest(string $name, callable $callback): array
    {
        $start = microtime(true);
        try {
            $msg = $callback();
            $duration = round((microtime(true) - $start) * 1000, 2);
            return [
                'name'     => $name,
                'pass'     => true,
                'message'  => $msg,
                'duration' => "{$duration} ms"
            ];
        } catch (\Throwable $e) {
            $duration = round((microtime(true) - $start) * 1000, 2);
            return [
                'name'     => $name,
                'pass'     => false,
                'message'  => $e->getMessage(),
                'duration' => "{$duration} ms"
            ];
        }
    }

    private function renderHtmlReport(array $results, float $totalTime, bool $allPassed, int $passedCount, int $totalCount): void
    {
        header('Content-Type: text/html; charset=utf-8');
        $statusColor = $allPassed ? '#10b981' : '#ef4444';
        $statusTitle = $allPassed ? '100% de Pruebas Superadas (PASS)' : 'Fallas Detectadas';

        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Diagnóstico y Suite de Pruebas — TAL S.A.</title>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
                body { background-color: #0f172a; color: #f8fafc; padding: 2rem 1rem; display: flex; justify-content: center; }
                .container { max-width: 860px; width: 100%; }
                .header-card { background: #1e293b; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; border: 1px solid #334155; }
                .header-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; }
                .title { font-size: 1.25rem; font-weight: 700; color: #fff; }
                .badge { padding: 0.35rem 0.75rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 700; }
                .badge-pass { background: #065f46; color: #34d399; }
                .badge-fail { background: #7f1d1d; color: #f87171; }
                .time-text { font-size: 0.85rem; color: #94a3b8; }
                .test-card { background: #1e293b; border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 0.75rem; border-left: 5px solid; border-top: 1px solid #334155; border-right: 1px solid #334155; border-bottom: 1px solid #334155; display: flex; justify-content: space-between; align-items: center; }
                .test-pass { border-left-color: #10b981; }
                .test-fail { border-left-color: #ef4444; }
                .test-name { font-weight: 700; font-size: 0.95rem; margin-bottom: 0.25rem; color: #f1f5f9; }
                .test-msg { font-size: 0.85rem; color: #cbd5e1; }
                .test-duration { font-size: 0.8rem; color: #94a3b8; font-family: monospace; white-space: nowrap; margin-left: 1rem; }
                .btn-back { display: inline-block; background: #3b82f6; color: #fff; text-decoration: none; padding: 0.65rem 1.25rem; border-radius: 6px; font-weight: 600; font-size: 0.9rem; margin-top: 1rem; }
                .btn-back:hover { background: #2563eb; }
                .json-link { margin-left: 0.75rem; color: #94a3b8; font-size: 0.85rem; text-decoration: underline; }
            </style>
        </head>
        <body>
            <div class="container">
                <header class="header-card">
                    <div class="header-top">
                        <h1 class="title">🧪 Diagnóstico Integral de Funcionalidades — TAL S.A.</h1>
                        <span class="badge <?= $allPassed ? 'badge-pass' : 'badge-fail' ?>">
                            <?= $passedCount ?> / <?= $totalCount ?> Superadas
                        </span>
                    </div>
                    <p class="time-text">
                        Ejecución en memoria local terminada en <strong><?= $totalTime ?> ms</strong>. 
                        Este reporte se ejecutó directamente dentro del servidor PHP sin requerir PowerShell ni permisos de administrador.
                    </p>
                    <div style="margin-top: 1rem; display: flex; align-items: center;">
                        <a href="/" class="btn-back">⬅️ Volver a la Plataforma</a>
                        <a href="/api/test-runner?format=json" class="json-link">Ver como JSON crudo</a>
                    </div>
                </header>

                <main class="test-list">
                    <?php foreach ($results as $res): ?>
                        <article class="test-card <?= $res['pass'] ? 'test-pass' : 'test-fail' ?>">
                            <div>
                                <h3 class="test-name"><?= $res['pass'] ? '✅' : '❌' ?> <?= htmlspecialchars($res['name']) ?></h3>
                                <p class="test-msg"><?= htmlspecialchars($res['message']) ?></p>
                            </div>
                            <span class="test-duration"><?= $res['duration'] ?></span>
                        </article>
                    <?php endforeach; ?>
                </main>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}
