<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Pledge;
use App\Repositories\ContributionRepositoryInterface;
use App\Repositories\PledgeRepositoryInterface;

/**
 * Keeps a pledge's paid amount and status true (D-047). It does not count up or down: it adds up the gifts that pay
 * the pledge, none of them voided, and saves that with the status that follows. It locks the pledge row first, so
 * gifts and voids at the same moment take turns, and it opens no transaction: the caller's does.
 */
class PledgeProgressService
{
    /**
     * Takes the repositories and the status rule.
     */
    public function __construct(
        private readonly PledgeRepositoryInterface $pledges,
        private readonly ContributionRepositoryInterface $contributions,
        private readonly PledgeStatusCalculator $status,
    ) {}

    /**
     * Locks the pledge, recalculates its paid amount and status from its gifts, and saves them.
     */
    public function refresh(string $pledgeId): Pledge
    {
        $pledge = $this->pledges->lockForUpdate($pledgeId);
        $paid = $this->contributions->totalForPledge($pledge->id);

        return $this->pledges->saveProgress($pledge, $paid, $this->status->for($pledge->amount_pledged, $paid, $pledge->target_date, now()));
    }
}
