<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Reports\MemberGrowth;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The member growth report (docs/08, Member Growth). Counts only: no member is named.
 *
 * @property MemberGrowth $resource
 */
class MemberGrowthResource extends JsonResource
{
    /** The report has `data` and `summary` side by side, so it is not wrapped in another `data`. */
    public static $wrap = null;

    /**
     * Shapes the report for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $report = $this->resource;

        return [
            'data' => $report->months,
            'summary' => ['new' => $report->new, 'lost' => $report->lost, 'net' => $report->net],
        ];
    }
}
