<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\DisbursementStatus;
use App\Models\Disbursement;
use App\Scopes\ChurchScope;
use App\ValueObjects\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

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

    /**
     * The ids of the payouts the provider has taken but not finished, last changed at or before this moment.
     *
     * @return Collection<int, string>
     */
    public function startedIdsChangedBefore(CarbonInterface $cutoff): Collection
    {
        return Disbursement::query()
            ->withoutGlobalScope(ChurchScope::class)
            ->whereNull('batch_id')
            ->where('status', DisbursementStatus::Processing->value)
            ->whereNotNull('provider_reference')
            ->where('updated_at', '<=', $cutoff)
            ->orderBy('updated_at')
            ->pluck('id');
    }

    /**
     * The ids of the payouts still unfinished and last changed at or before this moment, never a gift inside a batch.
     *
     * @return Collection<int, string>
     */
    public function unfinishedIdsChangedBefore(CarbonInterface $cutoff): Collection
    {
        return Disbursement::query()
            ->withoutGlobalScope(ChurchScope::class)
            ->whereNull('batch_id')
            ->whereIn('status', [DisbursementStatus::Pending->value, DisbursementStatus::Processing->value, DisbursementStatus::Failed->value])
            ->where('updated_at', '<=', $cutoff)
            ->orderBy('updated_at')
            ->pluck('id');
    }

    /**
     * What each church has waiting to be batched, as exact decimal strings by church id.
     *
     * @return array<string, numeric-string>
     */
    public function waitingTotalsByChurch(CarbonInterface $cutoff): array
    {
        $rows = Disbursement::query()
            ->withoutGlobalScope(ChurchScope::class)
            ->whereNull('batch_id')
            ->whereNotNull('contribution_id')
            ->where('status', DisbursementStatus::Pending->value)
            ->where('updated_at', '<=', $cutoff)
            ->groupBy('church_id')
            ->selectRaw('church_id, SUM(requested_amount) AS total')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[(string) $row->getAttribute('church_id')] = Money::ghs((string) $row->getAttribute('total'))->amount;
        }

        return $totals;
    }

    /**
     * The payouts changed since this moment, read a few at a time.
     *
     * @return LazyCollection<int, Disbursement>
     */
    public function changedSince(CarbonInterface $since): LazyCollection
    {
        return Disbursement::query()
            ->withoutGlobalScope(ChurchScope::class)
            ->with(['contribution' => fn ($gift) => $gift->withoutGlobalScope(ChurchScope::class)])
            ->where('updated_at', '>=', $since)
            ->orderBy('id')
            ->lazy(500);
    }

    /**
     * Whether any payout inside this batch is not paid.
     */
    public function hasUnpaidInBatch(string $batchId): bool
    {
        return Disbursement::query()->withoutGlobalScope(ChurchScope::class)->where('batch_id', $batchId)->where('status', '!=', DisbursementStatus::Paid->value)->exists();
    }

    /**
     * The ids of the batches changed since this moment whose requested amount is not the total of the payouts inside them.
     *
     * @return list<string>
     */
    public function batchIdsNotEqualToTheirGifts(CarbonInterface $since): array
    {
        $inside = Disbursement::query()
            ->withoutGlobalScope(ChurchScope::class)
            ->whereNotNull('batch_id')
            ->groupBy('batch_id')
            ->selectRaw('batch_id, SUM(requested_amount) AS total')
            ->pluck('total', 'batch_id');

        $wrong = [];

        foreach (Disbursement::query()->withoutGlobalScope(ChurchScope::class)->whereNull('contribution_id')->where('updated_at', '>=', $since)->get(['id', 'requested_amount']) as $batch) {
            $total = Money::ghs((string) ($inside[$batch->id] ?? '0'))->amount;

            if (bccomp($total, Money::ghs($batch->requested_amount)->amount, 2) !== 0) {
                $wrong[] = $batch->id;
            }
        }

        return $wrong;
    }
}
