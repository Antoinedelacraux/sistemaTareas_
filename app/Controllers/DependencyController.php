<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Models\Task;
use App\Models\TaskDependency;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Controlador de Dependencias entre Tareas (Grafo Acíclico Dirigido - DAG).
 * Endpoints:
 * - GET    /api/tareas/{id}/dependencias
 * - POST   /api/tareas/{id}/dependencias
 * - DELETE /api/tareas/{id}/dependencias/{pred_id}
 */
class DependencyController
{
    /**
     * Lista las dependencias (predecesoras y sucesoras) de una tarea.
     */
    public function index(array $request): void
    {
        $currentUser = $request['user'] ?? null;
        if (!$currentUser) {
            Response::error('Autenticación requerida.', 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $tareaId = (int)($request['params']['id'] ?? 0);
        $tarea = Task::findById($tareaId);
        if (!$tarea) {
            Response::error('Tarea no encontrada.', 404, 'ERR_TASK_NOT_FOUND');
            return;
        }

        if (!Task::canView($currentUser, $tarea)) {
            Response::error('No tienes permiso para ver esta tarea.', 403, 'ERR_FORBIDDEN_TASK_ACCESS');
            return;
        }

        try {
            $data = TaskDependency::getByTask($tareaId, $currentUser);
            Response::json($data, 200);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500, 'ERR_INTERNAL_ERROR');
        }
    }

    /**
     * Registra una nueva relación de precedencia en la tarea.
     */
    public function store(array $request): void
    {
        $currentUser = $request['user'] ?? null;
        if (!$currentUser) {
            Response::error('Autenticación requerida.', 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $tareaId = (int)($request['params']['id'] ?? 0);
        $body = $request['body'] ?? [];
        $predecesoraId = isset($body['predecesora_id']) ? (int)$body['predecesora_id'] : 0;

        if ($predecesoraId <= 0) {
            Response::error("El parámetro 'predecesora_id' es obligatorio y debe ser un ID válido.", 422, 'ERR_VALIDATION_FAILED');
            return;
        }

        try {
            $result = TaskDependency::add($tareaId, $predecesoraId, $currentUser);
            Response::json($result, 201);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 422, 'ERR_VALIDATION_FAILED');
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code <= 500) ? $code : 400;
            $errCode = 'ERR_DEPENDENCY_ERROR';

            if ($statusCode === 404) $errCode = 'ERR_TASK_NOT_FOUND';
            elseif ($statusCode === 403) $errCode = 'ERR_FORBIDDEN_ACTION';
            elseif ($statusCode === 409) {
                $errCode = str_contains(strtolower($e->getMessage()), 'ciclo')
                    ? 'ERR_CYCLIC_DEPENDENCY'
                    : 'ERR_DEPENDENCY_EXISTS';
            }

            Response::error($e->getMessage(), $statusCode, $errCode);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500, 'ERR_INTERNAL_ERROR');
        }
    }

    /**
     * Elimina una relación de precedencia de la tarea.
     */
    public function destroy(array $request): void
    {
        $currentUser = $request['user'] ?? null;
        if (!$currentUser) {
            Response::error('Autenticación requerida.', 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $tareaId = (int)($request['params']['id'] ?? 0);
        $predecesoraId = (int)($request['params']['pred_id'] ?? 0);

        try {
            $removed = TaskDependency::remove($tareaId, $predecesoraId, $currentUser);
            if (!$removed) {
                Response::error('La relación de dependencia no existe o ya fue eliminada.', 404, 'ERR_DEPENDENCY_NOT_FOUND');
                return;
            }

            Response::json(['mensaje' => 'Dependencia eliminada exitosamente.'], 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code <= 500) ? $code : 400;
            Response::error($e->getMessage(), $statusCode, ($statusCode === 403) ? 'ERR_FORBIDDEN_ACTION' : 'ERR_DEPENDENCY_ERROR');
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500, 'ERR_INTERNAL_ERROR');
        }
    }
}
