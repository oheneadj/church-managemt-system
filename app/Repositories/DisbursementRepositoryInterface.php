<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Disbursement;

/**
 * Every query on the automatic payouts to churches lives behind this contract (D-018).
 */
interface DisbursementRepositoryInterface
{
    /**
     * Records the payout owed for one completed gift. There is one payout for each gift, so asking again for the same gift returns the
     * one that exists and never makes a second.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createForGift(array $attributes): Disbursement;
}
