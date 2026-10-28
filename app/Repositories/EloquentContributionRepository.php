<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Contribution;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent implementation of the contribution totals.
 */
class EloquentContributionRepository implements ContributionRepositoryInterface
{
    /**
     * Adds up a member's unvoided gifts since the given moment, or all of them.
     */
    public function totalForMember(string $memberId, ?DateTimeInterface $since): string
    {
        $sum = Contribution::query()
            ->where('member_id', $memberId)
            ->where('is_voided', false)
            ->when($since !== null, fn (Builder $q) => $q->where('contributed_at', '>=', $since))
            ->sum('amount');

        return bcadd((string) $sum, '0', 2);
    }
}
