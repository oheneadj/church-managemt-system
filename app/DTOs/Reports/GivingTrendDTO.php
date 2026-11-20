<?php

declare(strict_types=1);

namespace App\DTOs\Reports;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Reports\GivingTrendRequest;
use App\Models\Church;
use LogicException;

/**
 * What the giving trend is asked for: the church (for its time zone), how many months back and, optionally, one fund.
 */
final readonly class GivingTrendDTO
{
    use ResolvesActor;

    /**
     * Holds the church, the number of months and the fund to look at, if one.
     */
    public function __construct(public Church $church, public int $months, public ?string $fundTypeId = null) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(GivingTrendRequest $request): self
    {
        $v = $request->validated();

        return new self(
            self::actorFrom($request)->church ?? throw new LogicException('A staff member always belongs to a church.'),
            (int) ($v['months'] ?? config('reports.giving_months_default')),
            $v['fund_type_id'] ?? null,
        );
    }
}
