<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Disbursement;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

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

    /**
     * The payout with this id, if there is one. The queued payout job uses it: no church is bound there, so it finds it wherever it is.
     */
    public function find(string $id): ?Disbursement;

    /**
     * The payout with this id, locked until the transaction ends, or null when there is none. A payout is claimed under this lock, so
     * two workers never send the same one.
     */
    public function lockById(string $id): ?Disbursement;

    /**
     * Saves a change to a payout.
     */
    public function save(Disbursement $disbursement): Disbursement;

    /**
     * The ids of the payouts that are due to be sent now, in every church, oldest first: those still pending; those that failed, have
     * attempts left and last changed long enough ago; and those started but never given a provider reference, left for long enough.
     *
     * @return Collection<int, string>
     */
    public function dueIds(CarbonInterface $now, int $maxAttempts, int $retryAfterMinutes, int $stuckAfterMinutes): Collection;

    /**
     * The ids of the churches that have payouts waiting to be put into a bank batch: a gift's own payout, still pending, not yet in
     * a batch. Every church, oldest first.
     *
     * @return Collection<int, string>
     */
    public function churchIdsWithWaitingPayouts(): Collection;

    /**
     * The payouts of one church waiting for a bank batch, locked until the transaction ends, oldest first.
     *
     * @return Collection<int, Disbursement>
     */
    public function lockWaitingFor(string $churchId): Collection;

    /**
     * Makes the batch payout, with no gift of its own, and puts the given gift payouts into it, marking them as started. They are
     * paid, or fail, with the batch.
     *
     * @param  array<string, mixed>  $attributes
     * @param  Collection<int, Disbursement>  $payouts
     */
    public function createBatch(array $attributes, Collection $payouts): Disbursement;

    /**
     * Marks every payout in a batch paid at this moment. Returns how many.
     */
    public function markBatchPaid(string $batchId, CarbonInterface $at): int;

    /**
     * The ids of the payouts the provider has taken but not finished: processing, with a provider reference, sent on their own or as a
     * batch (never a gift inside a batch), last changed at or before this moment. Every church, oldest first.
     *
     * @return Collection<int, string>
     */
    public function startedIdsChangedBefore(CarbonInterface $cutoff): Collection;

    /**
     * The ids of the payouts that are still unfinished (pending, processing or failed) and were last changed at or before this moment,
     * in every church, never a gift inside a batch (its batch is the payout that matters). Oldest first.
     *
     * @return Collection<int, string>
     */
    public function unfinishedIdsChangedBefore(CarbonInterface $cutoff): Collection;

    /**
     * What each church has waiting to be batched: the total of its pending payouts that are in no batch and were last changed at or
     * before this moment, as exact decimal strings by church id.
     *
     * @return array<string, numeric-string>
     */
    public function waitingTotalsByChurch(CarbonInterface $cutoff): array;

    /**
     * The payouts changed since this moment, with the gift each pays, read a few at a time, for the figures to be checked.
     *
     * @return LazyCollection<int, Disbursement>
     */
    public function changedSince(CarbonInterface $since): LazyCollection;

    /**
     * Whether any payout inside this batch is not paid.
     */
    public function hasUnpaidInBatch(string $batchId): bool;

    /**
     * The ids of the batches changed since this moment whose requested amount is not the total of the payouts inside them.
     *
     * @return list<string>
     */
    public function batchIdsNotEqualToTheirGifts(CarbonInterface $since): array;
}
