<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Health\CheckHealthAction;
use App\Http\Resources\HealthReportResource;
use Illuminate\Http\JsonResponse;

/**
 * Readiness probe for the load balancer, Coolify's deploy gate and uptime monitors.
 * It is public on purpose and reveals only ok or error per dependency. Liveness is Laravel's own /up.
 */
class HealthController extends Controller
{
    public function __construct(private readonly CheckHealthAction $action) {}

    public function __invoke(): JsonResponse
    {
        $report = $this->action->execute();

        return (new HealthReportResource($report))->response()->setStatusCode($report->healthy ? 200 : 503);
    }
}
