<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Services\DashboardService;
use Throwable;

/**
 * Controlador de Métricas y Dashboard Operativo (RF-19).
 */
class DashboardController
{
    /**
     * Retorna el conjunto de KPIs e indicadores operacionales según el alcance solicitado.
     */
    public function metrics(array $request): void
    {
        $user = $request['user'] ?? null;
        if (!$user) {
            Response::error('Autenticación requerida.', 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $scope = isset($request['query']['scope']) ? trim((string)$request['query']['scope']) : 'area';

        try {
            $metrics = DashboardService::getMetrics($user, $scope);
            Response::json($metrics, 200);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500, 'ERR_INTERNAL_ERROR');
        }
    }
}
