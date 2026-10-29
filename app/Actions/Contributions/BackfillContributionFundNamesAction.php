<?php

declare(strict_types=1);

namespace App\Actions\Contributions;

use App\Repositories\ContributionRepositoryInterface;

/**
 * Fills the fund name on gifts that have none, from the fund's name now, which is the best that is known for gifts
 * recorded before names were kept. It is the work of the contributions:backfill-fund-names command.
 */
final class BackfillContributionFundNamesAction
{
    /**
     * Takes the contribution repository.
     */
    public function __construct(private readonly ContributionRepositoryInterface $contributions) {}

    /**
     * Fills every missing name, a batch at a time, and returns how many gifts were filled.
     */
    public function execute(): int
    {
        return $this->contributions->backfillFundNames(config('giving.backfill_chunk'));
    }
}
