<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Models\Task;
use App\Models\User;

/**
 * Controlador de Historial de Tareas Archivadas (>24 horas finalizadas).
 * Acceso restringido exclusivamente para Jefatura de Área y Jefatura de Personal (RN-05).
 */
class HistoryController
{
    /**
     * Lista las tareas que han sido finalizadas hace más de 24 horas.
     */
    public function index(array $request): void
    {
        $user = $request['user'] ?? null;
        if (!$user) {
            Response::error('Sesión no iniciada.', 401, 'ERR_UNAUTHORIZED');
            return;
        }

        if (!User::isJefe($user)) {
            Response::error(
                'Acceso exclusivo para Jefatura de Área y Jefatura de Personal.',
                403,
                'ERR_FORBIDDEN_HISTORY_ACCESS'
            );
            return;
        }

        $filters = $request['query'] ?? [];
        $archivedTasks = Task::listArchived($user, $filters);

        Response::json([
            'tareas'     => $archivedTasks,
            'total'      => count($archivedTasks),
            'cutoff_24h' => gmdate('Y-m-d H:i:s', time() - 86400)
        ]);
    }

    /**
     * Elimina permanentemente una tarea que ya pertenece al historial.
     * DELETE /api/historial/{id}
     */
    public function delete(array $request): void
    {
        $user = $request['user'] ?? null;
        if (!$user) {
            Response::error('Sesión no iniciada.', 401, 'ERR_UNAUTHORIZED');
            return;
        }

        if (!User::isJefe($user)) {
            Response::error(
                'Acceso exclusivo para Jefatura de Área y Jefatura de Personal.',
                403,
                'ERR_FORBIDDEN_HISTORY_ACCESS'
            );
            return;
        }

        $taskId = (int)($request['params']['id'] ?? 0);
        if ($taskId <= 0) {
            Response::error('Identificador de tarea inválido.', 400, 'ERR_INVALID_ID');
            return;
        }

        Task::ensureHistorySchema();
        $task = Task::findById($taskId);
        if (!$task || !Task::isArchived($task)) {
            Response::error('La tarea no pertenece al historial archivado.', 404, 'ERR_HISTORY_TASK_NOT_FOUND');
            return;
        }

        if (!Task::delete($taskId, $user, 'ELIMINAR_HISTORIAL')) {
            Response::error('No se pudo eliminar la tarea del historial.', 500, 'ERR_HISTORY_DELETE_FAILED');
            return;
        }

        Response::json([
            'tarea_id' => $taskId,
            'codigo'   => $task['codigo'],
            'mensaje'  => 'La tarea fue eliminada permanentemente del historial.'
        ]);
    }
}
