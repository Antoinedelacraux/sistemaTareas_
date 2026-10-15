<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Models\Task;
use App\Models\User;
use App\Models\RealtimeEvent;

/**
 * Controlador de Gestión de Tareas (Fase 1: MVP-03).
 * Implementa el CRUD de tareas personales y del área con aplicación estricta
 * de reglas de asignación, visibilidad por alcance y auditoría
 * (RF-03, RF-04, RF-05, RF-06, RF-07, RF-09, RN-01, RN-02, RN-03, RN-04, RN-05).
 */
class TaskController
{
    /**
     * Lista tareas según el alcance y filtros del usuario autenticado.
     * GET /api/tareas
     */
    public function index(array $request): void
    {
        $currentUser = $request['user'] ?? null;
        if (!$currentUser) {
            Response::error("Autenticación requerida.", 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $filters = $request['query'] ?? [];
        $tareas = Task::listForUser($currentUser, $filters);

        Response::json([
            'tareas' => $tareas,
            'total'  => count($tareas)
        ]);
    }

    /**
     * Retorna el detalle de una tarea específica con protección Anti-IDOR.
     * GET /api/tareas/{id}
     */
    public function show(array $request): void
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

        $task = Task::findById($id);
        if ($task === null) {
            Response::error("Tarea no encontrada.", 404, 'ERR_TASK_NOT_FOUND');
            return;
        }

        // Validación de permisos y alcance en backend (Anti-IDOR)
        if (!Task::canView($currentUser, $task)) {
            Response::error(
                "No posee autorización para consultar esta tarea (RF-07, RN-02).",
                403,
                'ERR_FORBIDDEN_TASK_ACCESS'
            );
            return;
        }

        try {
            $aprobacion = \App\Models\Approval::findPendienteByTaskId($id);
            $task['aprobacion_pendiente'] = $aprobacion;
        } catch (\Throwable $e) {
            $task['aprobacion_pendiente'] = null;
        }

        Response::json([
            'tarea' => $task
        ]);
    }

    /**
     * Crea una nueva tarea del área.
     * POST /api/tareas
     */
    public function create(array $request): void
    {
        $currentUser = $request['user'] ?? null;
        if (!$currentUser) {
            Response::error("Autenticación requerida.", 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $body = $request['body'] ?? [];

        // Validación de título
        $titulo = trim((string)($body['titulo'] ?? ''));
        if ($titulo === '' || mb_strlen($titulo) > 255) {
            Response::error(
                "El título de la tarea es obligatorio y no puede exceder 255 caracteres.",
                422,
                'ERR_VALIDATION_FAILED'
            );
            return;
        }

        // Validación de prioridad
        $prioridad = strtoupper(trim((string)($body['prioridad'] ?? Task::PRIORIDAD_MEDIA)));
        if (!in_array($prioridad, [Task::PRIORIDAD_BAJA, Task::PRIORIDAD_MEDIA, Task::PRIORIDAD_ALTA, Task::PRIORIDAD_URGENTE], true)) {
            Response::error("La prioridad especificada no es válida.", 422, 'ERR_VALIDATION_FAILED');
            return;
        }
        $body['prioridad'] = $prioridad;

        // El sistema opera con un único tablero compartido de Área.
        $body['tipo_tarea'] = Task::TIPO_AREA;

        // Validación de fecha límite si fue provista
        if (!empty($body['fecha_limite'])) {
            $fecha = trim((string)$body['fecha_limite']);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                Response::error("El formato de fecha límite debe ser YYYY-MM-DD.", 422, 'ERR_VALIDATION_FAILED');
                return;
            }
        }

        // REGLA CRÍTICA RF-05, RN-03: Asistentes no pueden asignar a otros
        $isJefe = (bool)($currentUser['es_jefe'] ?? false);
        if (!$isJefe) {
            if (isset($body['responsable_id']) && (int)$body['responsable_id'] !== (int)$currentUser['id']) {
                Response::error(
                    "Los asistentes no tienen autorización para asignar tareas a otros usuarios (RF-05, RN-03).",
                    403,
                    'ERR_FORBIDDEN_ASSIGNMENT'
                );
                return;
            }
            $body['responsable_id'] = (int)$currentUser['id'];
        } else {
            // Si es jefe y envía responsable_id, validar que exista y esté activo
            if (!empty($body['responsable_id'])) {
                $validation = User::validateAssignment($currentUser, (int)$body['responsable_id']);
                if (!$validation['valid']) {
                    Response::error($validation['message'], 403, $validation['code']);
                    return;
                }
            } else {
                $body['responsable_id'] = (int)$currentUser['id'];
            }
        }

        $task = Task::create($body, $currentUser);
        RealtimeEvent::publishTask('TASK_CREATED', $task, $currentUser);

        Response::json([
            'tarea'   => $task,
            'mensaje' => 'Tarea creada exitosamente.'
        ], 201);
    }

    /**
     * Actualiza una tarea existente.
     * PUT /api/tareas/{id}
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
            Response::error("Identificador de tarea inválido.", 400, 'ERR_INVALID_ID');
            return;
        }

        $task = Task::findById($id);
        if ($task === null) {
            Response::error("Tarea no encontrada.", 404, 'ERR_TASK_NOT_FOUND');
            return;
        }

        // Validar permisos de edición
        if (!Task::canEdit($currentUser, $task)) {
            Response::error("No posee permisos para modificar esta tarea.", 403, 'ERR_FORBIDDEN_ACTION');
            return;
        }

        $body = $request['body'] ?? [];

        if (!User::isJefe($currentUser) && array_key_exists('fecha_limite', $body)) {
            $requestedDate = trim((string)($body['fecha_limite'] ?? ''));
            $currentDate = trim((string)($task['fecha_limite'] ?? ''));
            if ($requestedDate !== $currentDate) {
                Response::error(
                    'Los asistentes no pueden modificar la fecha límite de una tarea.',
                    403,
                    'ERR_FORBIDDEN_DUE_DATE_CHANGE'
                );
                return;
            }
        }

        // Validación de título si viene en payload
        if (isset($body['titulo'])) {
            $titulo = trim((string)$body['titulo']);
            if ($titulo === '' || mb_strlen($titulo) > 255) {
                Response::error("El título no puede estar vacío ni exceder 255 caracteres.", 422, 'ERR_VALIDATION_FAILED');
                return;
            }
        }

        // Validación de prioridad si viene en payload
        if (isset($body['prioridad'])) {
            $prioridad = strtoupper(trim((string)$body['prioridad']));
            if (!in_array($prioridad, [Task::PRIORIDAD_BAJA, Task::PRIORIDAD_MEDIA, Task::PRIORIDAD_ALTA, Task::PRIORIDAD_URGENTE], true)) {
                Response::error("Prioridad inválida.", 422, 'ERR_VALIDATION_FAILED');
                return;
            }
            $body['prioridad'] = $prioridad;
        }

        // REGLA CRÍTICA RN-03: Asistente no puede transferir/reasignar a otro usuario
        $isJefe = User::isJefe($currentUser);
        if (isset($body['responsable_id'])) {
            $nuevoRespId = (int)$body['responsable_id'];
            if (!$isJefe && $nuevoRespId !== (int)$task['responsable_id']) {
                Response::error(
                    "Los asistentes no tienen autorización para transferir o reasignar tareas a otros compañeros (RN-03).",
                    403,
                    'ERR_FORBIDDEN_REASSIGNMENT'
                );
                return;
            }

            if ($isJefe) {
                $validation = User::validateAssignment($currentUser, $nuevoRespId);
                if (!$validation['valid']) {
                    Response::error($validation['message'], 403, $validation['code']);
                    return;
                }
            }
        }

        $updated = Task::update($id, $body, $currentUser);
        RealtimeEvent::publishTask(
            isset($body['responsable_id']) && (int)$body['responsable_id'] !== (int)$task['responsable_id'] ? 'TASK_ASSIGNED' : 'TASK_UPDATED',
            $updated,
            $currentUser,
            $task
        );

        Response::json([
            'tarea'   => $updated,
            'mensaje' => 'Tarea actualizada exitosamente.'
        ]);
    }

    /**
     * Elimina una tarea existente.
     * DELETE /api/tareas/{id}
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
            Response::error("Identificador de tarea inválido.", 400, 'ERR_INVALID_ID');
            return;
        }

        $task = Task::findById($id);
        if ($task === null) {
            Response::error("Tarea no encontrada.", 404, 'ERR_TASK_NOT_FOUND');
            return;
        }

        if (!Task::canDelete($currentUser, $task)) {
            Response::error("No posee permisos para eliminar esta tarea.", 403, 'ERR_FORBIDDEN_ACTION');
            return;
        }

        Task::delete($id, $currentUser);
        RealtimeEvent::publishTask('TASK_DELETED', $task, $currentUser);

        Response::json([
            'mensaje' => 'Tarea eliminada exitosamente.'
        ]);
    }

    /**
     * Cierra una tarea por decisión de Jefatura y la deja disponible de inmediato en Historial.
     * POST /api/tareas/{id}/finalizar-inmediatamente
     */
    public function finalizeImmediately(array $request): void
    {
        $currentUser = $request['user'] ?? null;
        if (!$currentUser) {
            Response::error('Autenticación requerida.', 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $id = (int)($request['params']['id'] ?? 0);
        if ($id <= 0) {
            Response::error('Identificador de tarea inválido.', 400, 'ERR_INVALID_ID');
            return;
        }

        $before = Task::findById($id);
        $result = Task::finalizeImmediately($id, $currentUser);
        if (!$result['success']) {
            Response::error($result['message'], $result['status'], $result['code']);
            return;
        }
        RealtimeEvent::publishTask('TASK_ARCHIVED', $result['tarea'], $currentUser, $before ?: []);

        Response::json([
            'tarea' => $result['tarea'],
            'mensaje' => $result['mensaje']
        ]);
    }

}
