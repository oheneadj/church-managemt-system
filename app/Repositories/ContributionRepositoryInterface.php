<?php

declare(strict_types=1);

namespace App\Repositories;

use DateTimeInterface;

/**
 * The contribution totals the member profile shows.
 */
interface ContributionRepositoryInterface
{
    /**
     * Total given by a member since a moment (or ever), as an exact decimal string. Voided gifts never count.
     */
    public function totalForMember(string $memberId, ?DateTimeInterface $since): string;
}
