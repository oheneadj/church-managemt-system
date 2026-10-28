<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\HealthCheck;
use Illuminate\Http\JsonResponse;

/**
 * Readiness probe for the load balancer, Coolify's deploy gate and uptime monitors.
 * It is public on purpose and reveals only ok or error per dependency. Liveness is Laravel's own /up.
 */
class HealthController extends Controller
{
    public function __invoke(HealthCheck $health): JsonResponse
    {
        $checks = $health->run();
        $healthy = ! in_array('error', $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }
}
