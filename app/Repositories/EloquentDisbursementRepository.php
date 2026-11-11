<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Disbursement;

/**
 * Eloquent implementation of the payout queries.
 */
class EloquentDisbursementRepository implements DisbursementRepositoryInterface
{
    /**
     * Records the payout owed for one completed gift, or returns the one already recorded.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createForGift(array $attributes): Disbursement
    {
        return Disbursement::query()->firstOrCreate(['contribution_id' => $attributes['contribution_id']], $attributes);
    }
}
