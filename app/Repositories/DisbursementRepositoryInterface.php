<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Disbursement;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

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
}
