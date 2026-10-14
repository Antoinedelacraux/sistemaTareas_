<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;
use App\Models\Task;
use App\Models\User;
use Throwable;

/**
 * Motor de Automatizaciones y Disparadores de Eventos del Sistema (MVP-08).
 * Centraliza la lógica de negocio reactiva para notificaciones e integraciones idempotentes (RN-15).
 */
class AutomationEngine
{
    /**
     * Automatización: Notifica al responsable cuando se le asigna una tarea.
     */
    public static function notifyTaskAssigned(array $task, array $assigner): void
    {
        $respId = (int)$task['responsable_id'];
        $assignerId = (int)$assigner['id'];

        // Si se autoasignó la tarea a sí mismo, no requiere notificación
        if ($respId === $assignerId) {
            return;
        }

        try {
            $idempotencyKey = "task_assign:{$task['id']}:{$respId}";
            Notification::create(
                $respId,
                Notification::EVENTO_TAREA_ASIGNADA,
                "Nueva Tarea Asignada: {$task['codigo']}",
                "{$assigner['nombre']} te ha asignado la tarea '{$task['titulo']}'.",
                'TAREA',
                (int)$task['id'],
                $idempotencyKey,
                "/tareas/{$task['id']}"
            );
        } catch (Throwable $e) {
            // No interrumpir el flujo principal si una notificación secundaria falla
        }
    }

    /**
     * Automatización: Notifica a la Jefatura o al revisor cuando una tarea pasa a revisión formal.
     */
    public static function notifyApprovalRequested(array $task, array $approval, array $requester): void
    {
        $revisorId = !empty($approval['revisor_id']) ? (int)$approval['revisor_id'] : null;

        try {
            if ($revisorId) {
                $targetIds = [$revisorId];
            } else {
                // Notificar a los jefes activos
                $targetIds = [];
                $jefePlan = User::findByRol(User::ROL_JEFE_PLANEAMIENTO);
                if ($jefePlan) $targetIds[] = (int)$jefePlan['id'];
                $jefePers = User::findByRol(User::ROL_JEFE_PERSONAL);
                if ($jefePers) $targetIds[] = (int)$jefePers['id'];
            }

            foreach ($targetIds as $targetId) {
                // No notificar al mismo solicitante si fuera jefe
                if ($targetId === (int)$requester['id']) {
                    continue;
                }

                $idempotencyKey = "appr_req:{$approval['id']}:{$targetId}";
                Notification::create(
                    $targetId,
                    Notification::EVENTO_APROBACION_SOLICITADA,
                    "Revisión Requerida: {$task['codigo']}",
                    "{$requester['nombre']} ha solicitado la aprobación formal para la tarea '{$task['titulo']}'.",
                    'TAREA',
                    (int)$task['id'],
                    $idempotencyKey,
                    "/tareas/{$task['id']}"
                );
            }
        } catch (Throwable $e) {
        }
    }

    /**
     * Automatización: Notifica al responsable y creador cuando una tarea es Aprobada o Devuelta.
     */
    public static function notifyApprovalResolved(array $task, array $approval, string $dictamen, array $reviewer): void
    {
        $recipients = array_unique([
            (int)$task['responsable_id'],
            (int)$task['creador_id']
        ]);

        $isAprobada = ($dictamen === 'APROBAR');
        $tipoEvento = $isAprobada ? Notification::EVENTO_TAREA_APROBADA : Notification::EVENTO_TAREA_DEVUELTA;
        $titulo = $isAprobada
            ? "Tarea Aprobada: {$task['codigo']}"
            : "Tarea Devuelta: {$task['codigo']}";

        $mensaje = $isAprobada
            ? "{$reviewer['nombre']} ha aprobado la tarea '{$task['titulo']}'. La tarea se encuentra Finalizada."
            : "{$reviewer['nombre']} ha devuelto la tarea '{$task['titulo']}' con observaciones: \"{$approval['observaciones_devolucion']}\".";

        try {
            foreach ($recipients as $userId) {
                if ($userId === (int)$reviewer['id']) {
                    continue;
                }

                $idempotencyKey = "appr_res:{$approval['id']}:{$dictamen}:{$userId}";
                Notification::create(
                    $userId,
                    $tipoEvento,
                    $titulo,
                    $mensaje,
                    'TAREA',
                    (int)$task['id'],
                    $idempotencyKey,
                    "/tareas/{$task['id']}"
                );
            }
        } catch (Throwable $e) {
        }
    }

    /**
     * Automatización: Notifica al responsable de una tarea cuando sus dependencias concluyen y se desbloquea en cascada.
     */
    public static function notifyTaskUnlocked(array $task, int $triggerTaskId): void
    {
        $respId = (int)$task['responsable_id'];

        try {
            $idempotencyKey = "task_unlock:{$task['id']}:{$triggerTaskId}";
            Notification::create(
                $respId,
                Notification::EVENTO_TAREA_DESBLOQUEADA,
                "Tarea Desbloqueada: {$task['codigo']}",
                "Todas las tareas predecesoras han finalizado con éxito. Ya puedes iniciar la tarea '{$task['titulo']}'.",
                'TAREA',
                (int)$task['id'],
                $idempotencyKey,
                "/tareas/{$task['id']}"
            );
        } catch (Throwable $e) {
        }
    }

    /**
     * Automatización: Notifica a los interesados cuando se publica un nuevo comentario o evidencia.
     */
    public static function notifyNewComment(array $task, array $comment, array $author): void
    {
        $authorId = (int)$author['id'];
        $recipients = array_unique([
            (int)$task['responsable_id'],
            (int)$task['creador_id']
        ]);

        $preview = mb_substr($comment['comentario'] ?? '', 0, 75);
        if (mb_strlen($comment['comentario'] ?? '') > 75) {
            $preview .= '...';
        }

        try {
            foreach ($recipients as $userId) {
                if ($userId === $authorId) {
                    continue; // No notificar al propio autor del comentario
                }

                $idempotencyKey = "comment:{$comment['id']}:{$userId}";
                Notification::create(
                    $userId,
                    Notification::EVENTO_NUEVO_COMENTARIO,
                    "Nuevo Comentario: {$task['codigo']}",
                    "{$author['nombre']} comentó: \"{$preview}\"",
                    'TAREA',
                    (int)$task['id'],
                    $idempotencyKey,
                    "/tareas/{$task['id']}"
                );
            }
        } catch (Throwable $e) {
        }
    }
}
