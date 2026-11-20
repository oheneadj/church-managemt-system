<?php

declare(strict_types=1);

namespace App\DTOs\Reports;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Reports\MemberGrowthRequest;
use App\Models\Church;
use LogicException;

/**
 * What the member growth report is asked for: the church (for its time zone) and how many months back.
 */
final readonly class MemberGrowthDTO
{
    use ResolvesActor;

    /**
     * Holds the church and the number of months.
     */
    public function __construct(public Church $church, public int $months) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(MemberGrowthRequest $request): self
    {
        return new self(
            self::actorFrom($request)->church ?? throw new LogicException('A staff member always belongs to a church.'),
            (int) ($request->validated()['months'] ?? config('reports.members_months_default')),
        );
    }
}
