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
            ->whereNull('batch_id')
            ->where(function (Builder $due) use ($now, $maxAttempts, $retryAfterMinutes, $stuckAfterMinutes): void {
                $due->where('status', DisbursementStatus::Pending->value)
                    ->orWhere(fn (Builder $failed) => $failed->where('status', DisbursementStatus::Failed->value)->where('attempts', '<', $maxAttempts)->where('updated_at', '<=', $now->copy()->subMinutes($retryAfterMinutes)))
                    ->orWhere(fn (Builder $stuck) => $stuck->where('status', DisbursementStatus::Processing->value)->whereNull('provider_reference')->where('updated_at', '<=', $now->copy()->subMinutes($stuckAfterMinutes)));
            })
            ->orderBy('created_at')
            ->pluck('id');
    }

    /**
     * The ids of the churches that have payouts waiting to be put into a bank batch.
     *
     * @return Collection<int, string>
     */
    public function churchIdsWithWaitingPayouts(): Collection
    {
        return Disbursement::query()
            ->withoutGlobalScope(ChurchScope::class)
            ->whereNull('batch_id')
            ->whereNotNull('contribution_id')
            ->where('status', DisbursementStatus::Pending->value)
            ->orderBy('created_at')
            ->pluck('church_id')
            ->unique()
            ->values();
    }

    /**
     * The payouts of one church waiting for a bank batch, locked until the transaction ends, oldest first.
     *
     * @return Collection<int, Disbursement>
     */
    public function lockWaitingFor(string $churchId): Collection
    {
        return Disbursement::query()
            ->withoutGlobalScope(ChurchScope::class)
            ->where('church_id', $churchId)
            ->whereNull('batch_id')
            ->whereNotNull('contribution_id')
            ->where('status', DisbursementStatus::Pending->value)
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Makes the batch payout and puts the given gift payouts into it, marking them as started.
     *
     * @param  array<string, mixed>  $attributes
     * @param  Collection<int, Disbursement>  $payouts
     */
    public function createBatch(array $attributes, Collection $payouts): Disbursement
    {
        $batch = Disbursement::query()->create($attributes);

        Disbursement::query()
            ->withoutGlobalScope(ChurchScope::class)
            ->whereKey($payouts->pluck('id')->all())
            ->update(['batch_id' => $batch->id, 'status' => DisbursementStatus::Processing->value]);

        return $batch;
    }

    /**
     * Marks every payout in a batch paid at this moment.
     */
    public function markBatchPaid(string $batchId, CarbonInterface $at): int
    {
        return Disbursement::query()
            ->withoutGlobalScope(ChurchScope::class)
            ->where('batch_id', $batchId)
            ->update(['status' => DisbursementStatus::Paid->value, 'paid_at' => $at]);
    }
}
