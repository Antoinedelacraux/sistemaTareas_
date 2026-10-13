<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Models\BoardColumn;
use App\Models\Task;

/**
 * Controlador del Tablero Kanban y Transiciones de Estado (Fase 1: MVP-04).
 * Cubre: RF-08, RF-11, RF-12, RN-06, RN-10, RN-11, RN-12, RN-13.
 */
class BoardController
{
    /**
     * Retorna las columnas y estados configurados en el tablero.
     * GET /api/tableros/columnas
     */
    public function columns(array $request): void
    {
        $columnas = BoardColumn::listAllActive();
        Response::json([
            'columnas' => $columnas,
            'total'    => count($columnas)
        ]);
    }

    /**
     * Retorna el tablero Kanban completo con tareas agrupadas por columna
     * aplicando el alcance estricto del usuario en sesión (asistente vs jefatura).
     * GET /api/tableros/kanban
     */
    public function kanban(array $request): void
    {
        $currentUser = $request['user'] ?? null;
        if (!$currentUser) {
            Response::error("Autenticación requerida.", 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $filters = $request['query'] ?? [];
        $columnas = BoardColumn::listAllActive();
        $todasLasTareas = Task::listForUser($currentUser, $filters);

        // Agrupar tareas por columna_id
        $tareasPorColumna = [];
        foreach ($columnas as $col) {
            $tareasPorColumna[$col['id']] = [];
        }

        $resumen = [
            'total_tareas' => count($todasLasTareas),
            'pendientes'   => 0,
            'en_proceso'   => 0,
            'bloqueadas'   => 0,
            'en_revision'  => 0,
            'finalizadas'  => 0
        ];

        foreach ($todasLasTareas as $tarea) {
            $colId = (int)$tarea['columna_id'];
            if (isset($tareasPorColumna[$colId])) {
                $tareasPorColumna[$colId][] = $tarea;
            }

            // Acumular métricas según tipo_estado funcional
            $tipoEstado = $tarea['columna_tipo_estado'] ?? '';
            match ($tipoEstado) {
                BoardColumn::ESTADO_PENDIENTE   => $resumen['pendientes']++,
                BoardColumn::ESTADO_EN_PROCESO  => $resumen['en_proceso']++,
                BoardColumn::ESTADO_BLOQUEADA   => $resumen['bloqueadas']++,
                BoardColumn::ESTADO_EN_REVISION => $resumen['en_revision']++,
                BoardColumn::ESTADO_FINALIZADA  => $resumen['finalizadas']++,
                default => null
            };
        }

        $columnasAgrupadas = [];
        foreach ($columnas as $col) {
            $colId = (int)$col['id'];
            $tareasDeColumna = $tareasPorColumna[$colId] ?? [];
            $columnasAgrupadas[] = array_merge($col, [
                'total_tareas' => count($tareasDeColumna),
                'tareas'       => $tareasDeColumna
            ]);
        }

        Response::json([
            'columnas' => $columnasAgrupadas,
            'resumen'  => $resumen
        ]);
    }

    /**
     * Ejecuta la transición de una tarea a otra columna/estado Kanban.
     * Aplica reglas de negocio críticas para confirmación de cierre y requerimiento de aprobación.
     * POST /api/tareas/{id}/transicion
     */
    public function transition(array $request): void
    {
        $currentUser = $request['user'] ?? null;
        if (!$currentUser) {
            Response::error("Autenticación requerida.", 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $id = (int)($request['params']['id'] ?? 0);
        if ($id <= 0) {
            Response::error("Identificador de tarea inválido.", 400, 'ERR_INVALID_ID');
            return;
        }

        $body = $request['body'] ?? [];
        $nuevaColumnaId = (int)($body['nueva_columna_id'] ?? 0);

        if ($nuevaColumnaId <= 0) {
            Response::error("Debe proporcionar 'nueva_columna_id' válido.", 422, 'ERR_VALIDATION_FAILED');
            return;
        }

        $resultado = Task::transition($id, $nuevaColumnaId, $body, $currentUser);

        if (!$resultado['success']) {
            Response::error(
                $resultado['message'],
                $resultado['status'] ?? 400,
                $resultado['code'] ?? 'ERR_TRANSITION_FAILED'
            );
            return;
        }

        Response::json([
            'tarea'   => $resultado['tarea'],
            'mensaje' => $resultado['mensaje']
        ]);
    }
}
