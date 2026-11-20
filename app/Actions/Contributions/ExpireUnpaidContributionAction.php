<?php

declare(strict_types=1);

namespace App\Actions\Contributions;

use App\Enums\ContributionFailureReason;
use App\Enums\ContributionStatus;
use App\Models\Contribution;
use App\Repositories\ContributionRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Gives up waiting for a mobile money gift nobody approved (D-073). The gift becomes failed with the reason "expired", so it
 * is never left pending for ever. It is still asked about for a day afterwards, because a giver may approve late and then it is
 * counted (SettleContributionAction). A gift that was settled in the meantime is left alone: the row is locked and only a pending
 * gift is acted on.
 */
final class ExpireUnpaidContributionAction
{
    /**
     * Takes the contribution repository.
     */
    public function __construct(private readonly ContributionRepositoryInterface $contributions) {}

    /**
     * Expires the gift if it is still pending, and returns it as it now stands, or null when there is no such gift.
     */
    public function execute(string $contributionId): ?Contribution
    {
        return DB::transaction(function () use ($contributionId): ?Contribution {
            $gift = $this->contributions->lockById($contributionId);

            if ($gift === null || $gift->status !== ContributionStatus::Pending) {
                return $gift;
            }

            $gift->status = ContributionStatus::Failed;
            $gift->failure_reason = ContributionFailureReason::Expired;

            return $this->contributions->save($gift);
        });
    }
}
