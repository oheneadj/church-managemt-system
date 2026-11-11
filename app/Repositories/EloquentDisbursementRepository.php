<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\DisbursementStatus;
use App\Models\Disbursement;
use App\Scopes\ChurchScope;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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

    /**
     * The payout with this id, if there is one.
     */
    public function find(string $id): ?Disbursement
    {
        return Disbursement::query()->find($id);
    }

    /**
     * The payout with this id, locked until the transaction ends.
     */
    public function lockById(string $id): ?Disbursement
    {
        return Disbursement::query()->lockForUpdate()->find($id);
    }

    /**
     * Saves a change to a payout.
     */
    public function save(Disbursement $disbursement): Disbursement
    {
        $disbursement->save();

        return $disbursement;
    }

    /**
     * The ids of the payouts that are due to be sent now, in every church, oldest first.
     *
     * @return Collection<int, string>
     */
    public function dueIds(CarbonInterface $now, int $maxAttempts, int $retryAfterMinutes, int $stuckAfterMinutes): Collection
    {
        return Disbursement::query()
            ->withoutGlobalScope(ChurchScope::class)
            ->where(function (Builder $due) use ($now, $maxAttempts, $retryAfterMinutes, $stuckAfterMinutes): void {
                $due->where('status', DisbursementStatus::Pending->value)
                    ->orWhere(fn (Builder $failed) => $failed->where('status', DisbursementStatus::Failed->value)->where('attempts', '<', $maxAttempts)->where('updated_at', '<=', $now->copy()->subMinutes($retryAfterMinutes)))
                    ->orWhere(fn (Builder $stuck) => $stuck->where('status', DisbursementStatus::Processing->value)->whereNull('provider_reference')->where('updated_at', '<=', $now->copy()->subMinutes($stuckAfterMinutes)));
            })
            ->orderBy('created_at')
            ->pluck('id');
    }
}
