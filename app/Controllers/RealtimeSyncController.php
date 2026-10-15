<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Env;
use App\Core\Response;
use App\Models\RealtimeEvent;

/** Endpoint HTTP de espera larga para cambios autorizados en la SPA. */
class RealtimeSyncController
{
    public function cursor(array $request): void
    {
        $user = $request['user'] ?? null;
        if (!$user) {
            Response::error('Autenticación requerida.', 401, 'ERR_UNAUTHORIZED');
            return;
        }
        Response::json(['cursor' => RealtimeEvent::latestCursorForUser((int)$user['id'])]);
    }

    public function events(array $request): void
    {
        $user = $request['user'] ?? null;
        if (!$user) {
            Response::error('Autenticación requerida.', 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $query = $request['query'] ?? [];
        $after = max(0, (int)($query['after'] ?? 0));
        $configuredWait = (int)Env::get('REALTIME_LONG_POLL_SECONDS', 20);
        $maxWait = max(0, min(20, $configuredWait));
        $wait = isset($query['wait']) ? max(0, min($maxWait, (int)$query['wait'])) : $maxWait;
        $deadline = microtime(true) + $wait;
        $events = [];

        do {
            $events = RealtimeEvent::eventsForUser((int)$user['id'], $after);
            if ($events !== [] || microtime(true) >= $deadline) {
                break;
            }
            usleep(500000);
        } while (true);

        $cursor = $after;
        foreach ($events as $event) {
            $cursor = max($cursor, (int)$event['id']);
        }

        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('X-Accel-Buffering: no');
        }
        Response::json([
            'eventos' => $events,
            'cursor' => $cursor,
            'espera_agotada' => $events === [],
        ]);
    }
}
