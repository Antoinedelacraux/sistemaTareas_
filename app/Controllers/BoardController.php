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

    /**
     * Crea una nueva columna en el tablero (estilo Trello).
     * POST /api/tableros/columnas
     */
    public function createColumn(array $request): void
    {
        $currentUser = $request['user'] ?? null;
        if (!$currentUser) {
            Response::error("Autenticación requerida.", 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $isJefe = in_array($currentUser['rol'] ?? '', [\App\Models\User::ROL_JEFE_PERSONAL, \App\Models\User::ROL_JEFE_PLANEAMIENTO], true) || \App\Models\User::isJefe($currentUser);
        if (!$isJefe) {
            Response::error("Solo el personal de Jefatura está autorizado para gestionar columnas del tablero.", 403, 'ERR_FORBIDDEN_ACTION');
            return;
        }

        $body = $request['body'] ?? [];
        $nombre = trim((string)($body['nombre'] ?? ''));
        if ($nombre === '' || mb_strlen($nombre) > 50) {
            Response::error("El nombre de la columna es obligatorio y debe tener como máximo 50 caracteres.", 422, 'ERR_VALIDATION_FAILED');
            return;
        }

        $colorHex = trim((string)($body['color_hex'] ?? '#2563eb'));
        if (!preg_match('/^#[a-fA-F0-9]{6}$/', $colorHex)) {
            $colorHex = '#2563eb';
        }

        $rawTipo = strtoupper(trim((string)($body['tipo_estado'] ?? 'EN_PROCESO')));
        $tipoEstado = match ($rawTipo) {
            'REVISION', 'REVISION_APROBACION', BoardColumn::ESTADO_EN_REVISION => BoardColumn::ESTADO_EN_REVISION,
            'FINALIZADA', 'CUMPLIDA', BoardColumn::ESTADO_FINALIZADA => BoardColumn::ESTADO_FINALIZADA,
            'EN_PROCESO', 'EN_CURSO', BoardColumn::ESTADO_EN_PROCESO => BoardColumn::ESTADO_EN_PROCESO,
            'PENDIENTE', 'POR_HACER', BoardColumn::ESTADO_PENDIENTE => BoardColumn::ESTADO_PENDIENTE,
            'BLOQUEADA', BoardColumn::ESTADO_BLOQUEADA => BoardColumn::ESTADO_BLOQUEADA,
            default => BoardColumn::ESTADO_EN_PROCESO
        };

        $nuevaColumna = BoardColumn::create([
            'nombre'      => $nombre,
            'color_hex'   => $colorHex,
            'tipo_estado' => $tipoEstado
        ]);

        Response::json([
            'columna' => $nuevaColumna,
            'mensaje' => "Columna '{$nuevaColumna['nombre']}' creada exitosamente."
        ], 201);
    }

    /**
     * Actualiza el nombre, color o estado de una columna existente.
     * PUT /api/tableros/columnas/{id}
     */
    public function updateColumn(array $request): void
    {
        $currentUser = $request['user'] ?? null;
        if (!$currentUser) {
            Response::error("Autenticación requerida.", 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $isJefe = in_array($currentUser['rol'] ?? '', [\App\Models\User::ROL_JEFE_PERSONAL, \App\Models\User::ROL_JEFE_PLANEAMIENTO], true) || \App\Models\User::isJefe($currentUser);
        if (!$isJefe) {
            Response::error("Solo el personal de Jefatura está autorizado para gestionar columnas del tablero.", 403, 'ERR_FORBIDDEN_ACTION');
            return;
        }

        $id = (int)($request['params']['id'] ?? 0);
        if ($id <= 0) {
            Response::error("Identificador de columna inválido.", 400, 'ERR_INVALID_ID');
            return;
        }

        $body = $request['body'] ?? [];
        $actualizada = BoardColumn::update($id, $body);

        if (!$actualizada) {
            Response::error("Columna no encontrada.", 404, 'ERR_COLUMN_NOT_FOUND');
            return;
        }

        Response::json([
            'columna' => $actualizada,
            'mensaje' => "Columna actualizada exitosamente."
        ]);
    }

    /**
     * Elimina (desactiva) una columna si no tiene tareas.
     * DELETE /api/tableros/columnas/{id}
     */
    public function deleteColumn(array $request): void
    {
        $currentUser = $request['user'] ?? null;
        if (!$currentUser) {
            Response::error("Autenticación requerida.", 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $isJefe = in_array($currentUser['rol'] ?? '', [\App\Models\User::ROL_JEFE_PERSONAL, \App\Models\User::ROL_JEFE_PLANEAMIENTO], true) || \App\Models\User::isJefe($currentUser);
        if (!$isJefe) {
            Response::error("Solo el personal de Jefatura está autorizado para gestionar columnas del tablero.", 403, 'ERR_FORBIDDEN_ACTION');
            return;
        }

        $id = (int)($request['params']['id'] ?? 0);
        if ($id <= 0) {
            Response::error("Identificador de columna inválido.", 400, 'ERR_INVALID_ID');
            return;
        }

        try {
            BoardColumn::delete($id);
            Response::json([
                'mensaje' => "Columna eliminada exitosamente."
            ]);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 409, 'ERR_COLUMN_DELETE_FAILED');
        }
    }
}
