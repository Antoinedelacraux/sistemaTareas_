<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\RealtimeEvent;

/**
 * Controlador de Subtareas / Checklist dentro de Tareas (RF-10, RN-02).
 */
class SubtaskController
{
    /**
     * Lista las subtareas asociadas a una tarea.
     * GET /api/tareas/{id}/subtareas
     */
    public function index(array $request): void
    {
        $currentUser = $request['user'] ?? null;
        if (!$currentUser) {
            Response::error("Autenticación requerida.", 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $taskId = (int)($request['params']['id'] ?? 0);
        if ($taskId <= 0) {
            Response::error("Identificador de tarea inválido.", 400, 'ERR_INVALID_ID');
            return;
        }

        $task = Task::findById($taskId);
        if (!$task) {
            Response::error("Tarea no encontrada.", 404, 'ERR_TASK_NOT_FOUND');
            return;
        }

        if (!Task::canView($currentUser, $task)) {
            Response::error("No posee permisos para consultar subtareas de esta tarea.", 403, 'ERR_FORBIDDEN_TASK_ACCESS');
            return;
        }

        $subtareas = Subtask::listByTaskId($taskId);

        Response::json([
            'subtareas' => $subtareas,
            'total'     => count($subtareas)
        ]);
    }

    /**
     * Crea una nueva subtarea.
     * POST /api/tareas/{id}/subtareas
     */
    public function create(array $request): void
    {
        $currentUser = $request['user'] ?? null;
        if (!$currentUser) {
            Response::error("Autenticación requerida.", 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $taskId = (int)($request['params']['id'] ?? 0);
        if ($taskId <= 0) {
            Response::error("Identificador de tarea inválido.", 400, 'ERR_INVALID_ID');
            return;
        }

        $task = Task::findById($taskId);
        if (!$task) {
            Response::error("Tarea no encontrada.", 404, 'ERR_TASK_NOT_FOUND');
            return;
        }

        if (!Task::canManageChecklist($currentUser, $task)) {
            Response::error("No posee permisos para agregar subtareas a esta tarea o la tarea se encuentra finalizada.", 403, 'ERR_FORBIDDEN_ACTION');
            return;
        }

        $body = $request['body'] ?? [];
        $titulo = trim((string)($body['titulo'] ?? ''));

        if ($titulo === '' || mb_strlen($titulo) > 255) {
            Response::error("El título de la subtarea es obligatorio (máximo 255 caracteres).", 422, 'ERR_VALIDATION_FAILED');
            return;
        }

        $orden = (int)($body['orden'] ?? 1);
        $subtarea = Subtask::create($taskId, $titulo, $orden);
        RealtimeEvent::publishTask('CHECKLIST_CREATED', $task, $currentUser);

        Response::json([
            'subtarea' => $subtarea,
            'mensaje'  => 'Subtarea creada exitosamente.'
        ], 201);
    }

    /**
     * Actualiza una subtarea (marcar como completada o editar título).
     * PATCH /api/subtareas/{id}
     */
    public function update(array $request): void
    {
        $currentUser = $request['user'] ?? null;
        if (!$currentUser) {
            Response::error("Autenticación requerida.", 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $id = (int)($request['params']['id'] ?? 0);
        if ($id <= 0) {
            Response::error("Identificador de subtarea inválido.", 400, 'ERR_INVALID_ID');
            return;
        }

        $subtask = Subtask::findById($id);
        if (!$subtask) {
            Response::error("Subtarea no encontrada.", 404, 'ERR_SUBTASK_NOT_FOUND');
            return;
        }

        $task = Task::findById((int)$subtask['tarea_id']);
        if (!$task || !Task::canManageChecklist($currentUser, $task)) {
            Response::error("No posee permisos para modificar esta subtarea o la tarea se encuentra finalizada.", 403, 'ERR_FORBIDDEN_ACTION');
            return;
        }

        $body = $request['body'] ?? [];
        $updated = Subtask::update($id, $body);
        RealtimeEvent::publishTask('CHECKLIST_UPDATED', $task, $currentUser);

        Response::json([
            'subtarea' => $updated,
            'mensaje'  => 'Subtarea actualizada exitosamente.'
        ]);
    }

    /**
     * Elimina una subtarea.
     * DELETE /api/subtareas/{id}
     */
    public function delete(array $request): void
    {
        $currentUser = $request['user'] ?? null;
        if (!$currentUser) {
            Response::error("Autenticación requerida.", 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $id = (int)($request['params']['id'] ?? 0);
        if ($id <= 0) {
            Response::error("Identificador de subtarea inválido.", 400, 'ERR_INVALID_ID');
            return;
        }

        $subtask = Subtask::findById($id);
        if (!$subtask) {
            Response::error("Subtarea no encontrada.", 404, 'ERR_SUBTASK_NOT_FOUND');
            return;
        }

        $task = Task::findById((int)$subtask['tarea_id']);
        if (!$task || !Task::canManageChecklist($currentUser, $task)) {
            Response::error("No posee permisos para eliminar esta subtarea o la tarea se encuentra finalizada.", 403, 'ERR_FORBIDDEN_ACTION');
            return;
        }

        Subtask::delete($id);
        RealtimeEvent::publishTask('CHECKLIST_DELETED', $task, $currentUser);

        Response::json([
            'mensaje' => 'Subtarea eliminada exitosamente.'
        ]);
    }
}
