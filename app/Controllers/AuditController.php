<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Models\Audit;
use App\Models\Task;
use App\Models\User;
use Throwable;

/**
 * Controlador de Auditoría e Historial de Eventos (RF-18, RN-13).
 */
class AuditController
{
    /**
     * Retorna el historial cronológico completo de una tarea.
     * Aplica validación estricta de alcance y anti-IDOR (RN-02).
     */
    public function taskHistory(array $request): void
    {
        $user = $request['user'] ?? null;
        if (!$user) {
            Response::error('Autenticación requerida.', 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $taskId = (int)($request['params']['id'] ?? 0);
        $task = Task::findById($taskId);

        if (!$task) {
            Response::error('La tarea no existe.', 404, 'ERR_TASK_NOT_FOUND');
            return;
        }

        // Anti-IDOR: Validar que el usuario tenga acceso a ver la tarea
        if (!Task::canView($user, $task)) {
            Response::error('No tienes permiso para consultar el historial de esta tarea.', 403, 'ERR_FORBIDDEN_ACTION');
            return;
        }

        try {
            $limit = isset($request['query']['limit']) ? (int)$request['query']['limit'] : 100;
            $historial = Audit::getByTask($taskId, $limit);

            Response::json([
                'tarea_id'  => $taskId,
                'codigo'    => $task['codigo'],
                'titulo'    => $task['titulo'],
                'total'     => count($historial),
                'historial' => $historial
            ], 200);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500, 'ERR_INTERNAL_ERROR');
        }
    }

    /**
     * Retorna eventos recientes de auditoría del sistema.
     * Supervisión completa para Jefatura; filtrado para asistentes.
     */
    public function index(array $request): void
    {
        $user = $request['user'] ?? null;
        if (!$user) {
            Response::error('Autenticación requerida.', 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $isJefe = in_array($user['rol'] ?? '', [User::ROL_JEFE_PLANEAMIENTO, User::ROL_JEFE_PERSONAL], true);
        $limit = isset($request['query']['limit']) ? (int)$request['query']['limit'] : 50;
        $entityType = !empty($request['query']['entidad_tipo']) ? (string)$request['query']['entidad_tipo'] : null;

        // Si es asistente, solo puede consultar eventos donde él fue el actor
        $filterUserId = $isJefe ? (!empty($request['query']['usuario_id']) ? (int)$request['query']['usuario_id'] : null) : (int)$user['id'];

        try {
            $eventos = Audit::getRecent($limit, $entityType, $filterUserId);

            Response::json([
                'es_jefe' => $isJefe,
                'total'   => count($eventos),
                'eventos' => $eventos
            ], 200);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500, 'ERR_INTERNAL_ERROR');
        }
    }
}
