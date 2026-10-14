<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Models\Notification;
use Throwable;

/**
 * Controlador de Notificaciones Internas e Idempotentes (Fase 1: MVP-08).
 * Endpoints:
 * - GET   /api/notificaciones
 * - GET   /api/notificaciones/resumen
 * - PATCH /api/notificaciones/{id}/leer
 * - POST  /api/notificaciones/marcar-todas-leidas
 */
class NotificationController
{
    /**
     * Lista las notificaciones del usuario en sesión.
     */
    public function index(array $request): void
    {
        $user = $request['user'] ?? null;
        if (!$user) {
            Response::error('Autenticación requerida.', 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $userId = (int)$user['id'];
        $soloNoLeidas = !empty($request['query']['solo_no_leidas']) && (int)$request['query']['solo_no_leidas'] === 1;
        $limit = isset($request['query']['limit']) ? (int)$request['query']['limit'] : 50;

        try {
            $notificaciones = Notification::listByUser($userId, $soloNoLeidas, $limit);
            $unreadCount = Notification::countUnread($userId);

            Response::json([
                'notificaciones' => $notificaciones,
                'no_leidas'      => $unreadCount
            ], 200);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500, 'ERR_INTERNAL_ERROR');
        }
    }

    /**
     * Retorna el resumen del contador de notificaciones no leídas para la campana de la cabecera.
     */
    public function unreadCount(array $request): void
    {
        $user = $request['user'] ?? null;
        if (!$user) {
            Response::error('Autenticación requerida.', 401, 'ERR_UNAUTHORIZED');
            return;
        }

        try {
            $count = Notification::countUnread((int)$user['id']);
            $recientes = Notification::listByUser((int)$user['id'], true, 5);

            Response::json([
                'no_leidas' => $count,
                'recientes' => $recientes
            ], 200);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500, 'ERR_INTERNAL_ERROR');
        }
    }

    /**
     * Marca una notificación como leída con validación anti-IDOR.
     */
    public function markAsRead(array $request): void
    {
        $user = $request['user'] ?? null;
        if (!$user) {
            Response::error('Autenticación requerida.', 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $notifId = (int)($request['params']['id'] ?? 0);
        $notif = Notification::findById($notifId);

        if (!$notif) {
            Response::error('Notificación no encontrada.', 404, 'ERR_NOTIFICATION_NOT_FOUND');
            return;
        }

        // Anti-IDOR: No se puede marcar una notificación de otro usuario
        if ((int)$notif['usuario_id'] !== (int)$user['id']) {
            Response::error('No tienes permiso para acceder a esta notificación.', 403, 'ERR_FORBIDDEN_ACTION');
            return;
        }

        try {
            Notification::markAsRead($notifId, (int)$user['id']);
            Response::json(['mensaje' => 'Notificación marcada como leída.'], 200);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500, 'ERR_INTERNAL_ERROR');
        }
    }

    /**
     * Marca todas las notificaciones pendientes del usuario como leídas.
     */
    public function markAllAsRead(array $request): void
    {
        $user = $request['user'] ?? null;
        if (!$user) {
            Response::error('Autenticación requerida.', 401, 'ERR_UNAUTHORIZED');
            return;
        }

        try {
            $count = Notification::markAllAsRead((int)$user['id']);
            Response::json([
                'mensaje'             => 'Todas las notificaciones fueron marcadas como leídas.',
                'total_actualizadas' => $count
            ], 200);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500, 'ERR_INTERNAL_ERROR');
        }
    }
}
