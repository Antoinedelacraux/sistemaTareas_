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
            Response::unauthorized('Sesión no iniciada.');
            return;
        }

        if (!User::isJefe($user)) {
            Response::forbidden(
                'Acceso exclusivo para Jefatura de Área y Jefatura de Personal.',
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
}
