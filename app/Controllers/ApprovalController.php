<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Models\Approval;
use App\Models\Task;
use App\Models\User;
use InvalidArgumentException;
use RuntimeException;

/**
 * Controlador de Aprobaciones y Revisiones de Tareas (Fase 1: MVP-06).
 * Gestiona el circuito de solicitud de revisión, bandeja de pendientes para jefaturas,
 * aprobación con finalización atómica y devolución con observaciones obligatorias (RF-13, RN-10, RN-11, RN-12).
 */
class ApprovalController
{
    /**
     * Solicita la aprobación formal de una tarea y la transiciona a 'EN_REVISION'.
     * POST /api/tareas/{id}/solicitar-aprobacion
     */
    public function solicitar(array $request): void
    {
        $user = $request['user'] ?? null;
        if (!$user) {
            Response::error("Autenticación requerida.", 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $tareaId = (int)($request['params']['id'] ?? 0);
        $tarea = Task::findById($tareaId);

        if (!$tarea) {
            Response::error("La tarea no existe.", 404, 'ERR_TASK_NOT_FOUND');
            return;
        }

        // Validación de permisos de edición sobre la tarea
        if (!Task::canEdit($user, $tarea)) {
            Response::error(
                "No tienes autorización para solicitar la aprobación de esta tarea.",
                403,
                'ERR_FORBIDDEN_ACTION'
            );
            return;
        }

        if (empty($tarea['requiere_aprobacion'])) {
            Response::error(
                "Esta tarea no requiere aprobación de Jefatura y no puede entrar en revisión.",
                422,
                'ERR_APPROVAL_NOT_REQUIRED'
            );
            return;
        }

        $body = $request['body'] ?? [];
        $comentario = isset($body['comentario_entrega']) ? (string)$body['comentario_entrega'] : null;
        $revisorId = !empty($body['revisor_id']) ? (int)$body['revisor_id'] : null;

        try {
            $aprobacion = Approval::solicitar($tareaId, (int)$user['id'], $comentario, $revisorId);
            Response::json($aprobacion, 200);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400, 'ERR_APPROVAL_FAILED');
        } catch (\Throwable $e) {
            error_log("Error al solicitar aprobación: " . $e->getMessage());
            Response::error("Error interno al procesar la solicitud de aprobación.", 500, 'ERR_INTERNAL_SERVER');
        }
    }

    /**
     * Resuelve la aprobación de una tarea (APROBAR o DEVOLVER).
     * POST /api/tareas/{id}/resolver-aprobacion
     */
    public function resolver(array $request): void
    {
        $user = $request['user'] ?? null;
        if (!$user) {
            Response::error("Autenticación requerida.", 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $tareaId = (int)($request['params']['id'] ?? 0);
        $tarea = Task::findById($tareaId);

        if (!$tarea) {
            Response::error("La tarea no existe.", 404, 'ERR_TASK_NOT_FOUND');
            return;
        }

        // Validación de permisos: Solo jefaturas autorizadas o el revisor asignado
        if (!Approval::canResolve($user, $tarea)) {
            Response::error(
                "No tienes permisos para resolver la aprobación de esta tarea. Requiere rol de jefatura.",
                403,
                'ERR_FORBIDDEN_APPROVAL_ACTION'
            );
            return;
        }

        $body = $request['body'] ?? [];
        $accion = isset($body['accion']) ? strtoupper(trim((string)$body['accion'])) : '';
        $observaciones = isset($body['observaciones']) ? (string)$body['observaciones'] : null;

        if ($accion !== Approval::ACCION_APROBAR && $accion !== Approval::ACCION_DEVOLVER) {
            Response::error(
                "La acción debe ser 'APROBAR' o 'DEVOLVER'.",
                422,
                'ERR_VALIDATION_FAILED',
                ['accion' => "Debe ser 'APROBAR' o 'DEVOLVER'"]
            );
            return;
        }

        // Regla RN-12: Si se devuelve, las observaciones son obligatorias
        if ($accion === Approval::ACCION_DEVOLVER && ($observaciones === null || trim($observaciones) === '')) {
            Response::error(
                "Las observaciones son obligatorias al devolver una tarea (Regla RN-12).",
                422,
                'ERR_VALIDATION_FAILED',
                ['observaciones' => "Requerido para la devolución"]
            );
            return;
        }

        try {
            $resultado = Approval::resolver($tareaId, (int)$user['id'], $accion, $observaciones);
            Response::json($resultado, 200);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422, 'ERR_VALIDATION_FAILED');
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400, 'ERR_APPROVAL_RESOLUTION_FAILED');
        } catch (\Throwable $e) {
            error_log("Error al resolver aprobación: " . $e->getMessage());
            Response::error("Error interno al resolver la aprobación.", 500, 'ERR_INTERNAL_SERVER');
        }
    }

    /**
     * Lista la bandeja de aprobaciones pendientes para jefaturas (RF-13).
     * GET /api/aprobaciones/pendientes
     */
    public function pendientes(array $request): void
    {
        $user = $request['user'] ?? null;
        if (!$user) {
            Response::error("Autenticación requerida.", 401, 'ERR_UNAUTHORIZED');
            return;
        }

        // Solo jefes autorizados pueden consultar la bandeja general de aprobaciones
        if (!User::isJefe($user)) {
            Response::error(
                "Acceso restringido a personal de jefatura autorizada.",
                403,
                'ERR_FORBIDDEN_ACTION'
            );
            return;
        }

        try {
            $pendientes = Approval::listPendientesForUser($user);
            Response::json($pendientes, 200, ['total' => count($pendientes)]);
        } catch (\Throwable $e) {
            error_log("Error al listar aprobaciones pendientes: " . $e->getMessage());
            Response::error("Error interno al obtener la bandeja de aprobaciones.", 500, 'ERR_INTERNAL_SERVER');
        }
    }

    /**
     * Retorna el historial cronológico de revisiones y resoluciones de una tarea.
     * GET /api/tareas/{id}/aprobaciones
     */
    public function historial(array $request): void
    {
        $user = $request['user'] ?? null;
        if (!$user) {
            Response::error("Autenticación requerida.", 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $tareaId = (int)($request['params']['id'] ?? 0);
        $tarea = Task::findById($tareaId);

        if (!$tarea) {
            Response::error("La tarea no existe.", 404, 'ERR_TASK_NOT_FOUND');
            return;
        }

        // Validación anti-IDOR: solo usuarios con permiso de lectura sobre la tarea
        if (!Task::canView($user, $tarea)) {
            Response::error(
                "No tienes autorización para consultar las aprobaciones de esta tarea.",
                403,
                'ERR_FORBIDDEN_TASK_ACCESS'
            );
            return;
        }

        try {
            $historial = Approval::listByTaskId($tareaId);
            Response::json($historial, 200, ['total' => count($historial)]);
        } catch (\Throwable $e) {
            error_log("Error al consultar historial de aprobaciones: " . $e->getMessage());
            Response::error("Error interno al consultar el historial de aprobaciones.", 500, 'ERR_INTERNAL_SERVER');
        }
    }
}
