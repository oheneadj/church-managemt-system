<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Health\CheckHealthAction;
use App\Http\Resources\HealthReportResource;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Readiness probe for the load balancer, Coolify's deploy gate and uptime monitors.
 * It is public on purpose and reveals only ok or error per dependency. Liveness is Laravel's own /up.
 */
class HealthController extends Controller
{
    /**
     * Takes the check health action.
     */
    public function __construct(private readonly CheckHealthAction $action) {}

    /**
     * Runs the readiness checks and answers 200 when healthy, 503 when degraded.
     */
    #[Response(status: HttpResponse::HTTP_OK, description: 'Every check passed.', type: 'array{status: "ok", checks: array<string, "ok"|"error">, timestamp: string}')]
    #[Response(status: HttpResponse::HTTP_SERVICE_UNAVAILABLE, description: 'At least one check failed.', type: 'array{status: "degraded", checks: array<string, "ok"|"error">, timestamp: string}')]
    public function __invoke(): JsonResponse
    {
        $report = $this->action->execute();

        return (new HealthReportResource($report))->response()->setStatusCode($report->healthy ? HttpResponse::HTTP_OK : HttpResponse::HTTP_SERVICE_UNAVAILABLE);
    }
}
