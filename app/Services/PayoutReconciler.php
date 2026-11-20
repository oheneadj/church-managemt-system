<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DisbursementStatus;
use App\Enums\PayoutMethod;
use App\Models\Disbursement;
use App\Repositories\ChurchRepositoryInterface;
use App\Repositories\ContributionRepositoryInterface;
use App\Repositories\DisbursementRepositoryInterface;
use App\ValueObjects\Money;

/**
 * Checks that what was collected has been paid out, down to the pesewa (D-018, D-069 step e5). It looks across every church for what
 * should never happen and says what it found, as ids only, never an amount or a name:
 *
 * - `missing_payout`: a completed mobile money gift with no payout record at all, so the church would never be paid;
 * - `stuck_payout`: a payout still pending, processing or failed for more than a day;
 * - `unbatched_bank_payouts`: a bank church whose waiting payouts have been over the bank minimum for more than a day without a batch;
 * - `wrong_figures`: a payout whose own sums do not add up (what is requested is the gift less the platform fee, what the church received
 *   is what was requested less the provider's fee, the gift it pays is the gross), or a batch that does not equal the gifts inside it;
 * - `batch_inconsistent`: a paid batch with a gift in it that is not paid.
 */
final class PayoutReconciler
{
    /**
     * Takes the repositories.
     */
    public function __construct(
        private readonly ContributionRepositoryInterface $contributions,
        private readonly DisbursementRepositoryInterface $disbursements,
        private readonly ChurchRepositoryInterface $churches,
    ) {}

    /**
     * Runs every check and returns the ids found, by kind. A kind with nothing found is left out.
     *
     * @return array<string, array<int, string>>
     */
    public function findings(): array
    {
        $found = [
            'missing_payout' => $this->contributions->completedDigitalIdsWithoutPayout(now()->subMinutes((int) config('giving.reconcile_gift_grace_minutes')))->values()->all(),
            'stuck_payout' => $this->disbursements->unfinishedIdsChangedBefore(now()->subHours((int) config('giving.reconcile_stuck_after_hours')))->values()->all(),
            'unbatched_bank_payouts' => $this->unbatchedBankChurches(),
            ...$this->figuresThatDoNotAddUp(),
        ];

        return array_filter($found, fn (array $ids): bool => $ids !== []);
    }

    /**
     * The ids of the bank churches whose waiting payouts have sat over the bank minimum for more than a day without being batched.
     *
     * @return list<string>
     */
    private function unbatchedBankChurches(): array
    {
        $minimum = Money::ghs((string) config('giving.bank_payout_minimum'))->amount;
        $found = [];

        foreach ($this->disbursements->waitingTotalsByChurch(now()->subHours((int) config('giving.reconcile_stuck_after_hours'))) as $churchId => $total) {
            $church = $this->churches->find($churchId);

            if ($church?->payout_method === PayoutMethod::Bank && bccomp($total, $minimum, 2) >= 0) {
                $found[] = $churchId;
            }
        }

        return $found;
    }

    /**
     * The payouts whose sums do not add up, and the paid batches with a gift not paid.
     *
     * @return array{wrong_figures: list<string>, batch_inconsistent: list<string>}
     */
    private function figuresThatDoNotAddUp(): array
    {
        $wrong = [];
        $inconsistent = [];

        foreach ($this->disbursements->changedSince(now()->subDays((int) config('giving.reconcile_lookback_days'))) as $payout) {
            if (! $this->addsUp($payout)) {
                $wrong[] = $payout->id;
            }

            if ($payout->contribution_id === null && $payout->status === DisbursementStatus::Paid && $this->disbursements->hasUnpaidInBatch($payout->id)) {
                $inconsistent[] = $payout->id;
            }
        }

        foreach ($this->disbursements->batchIdsNotEqualToTheirGifts(now()->subDays((int) config('giving.reconcile_lookback_days'))) as $batchId) {
            $wrong[] = $batchId;
        }

        return ['wrong_figures' => array_values(array_unique($wrong)), 'batch_inconsistent' => $inconsistent];
    }

    /**
     * Whether this payout's own figures add up: the requested amount is the gross less the platform fee; when it is paid with a fee and a
     * net amount, the net is the requested less the fee; and a gift's own payout has the gift's amount as its gross.
     */
    private function addsUp(Disbursement $payout): bool
    {
        $gross = Money::ghs($payout->gross_amount)->amount;
        $requested = bcsub($gross, Money::ghs($payout->platform_fee)->amount, 2);

        if (bccomp($requested, Money::ghs($payout->requested_amount)->amount, 2) !== 0) {
            return false;
        }

        if ($payout->status === DisbursementStatus::Paid && $payout->provider_fee !== null && $payout->net_amount !== null
            && bccomp(bcsub($requested, Money::ghs($payout->provider_fee)->amount, 2), Money::ghs($payout->net_amount)->amount, 2) !== 0) {
            return false;
        }

        return $payout->contribution === null || bccomp(Money::ghs($payout->contribution->amount)->amount, $gross, 2) === 0;
    }
}
