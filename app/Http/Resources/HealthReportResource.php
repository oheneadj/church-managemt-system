<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Health\HealthReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The readiness report: ok or degraded, with ok or error per dependency and never the reason.
 *
 * @mixin HealthReport
 */
class HealthReportResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Shapes the health report for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->healthy ? 'ok' : 'degraded',
            'checks' => $this->checks,
            'timestamp' => $this->checkedAt->format(DATE_ATOM),
        ];
    }
}
