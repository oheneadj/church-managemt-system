<?php

declare(strict_types=1);

namespace App\Actions\Contributions;

use App\Enums\DisbursementStatus;
use App\Enums\PaymentProviderName;
use App\Enums\PayoutMethod;
use App\Jobs\SendDisbursementJob;
use App\Models\Disbursement;
use App\Repositories\ChurchRepositoryInterface;
use App\Repositories\DisbursementRepositoryInterface;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

/**
 * Puts a bank church's waiting payouts into one batch once they add up to the bank minimum (D-072, D-069 step e3). A bank payout must be
 * at least GHS 100, and most gifts are smaller, so a church that settles to a bank is paid when its settled gifts reach the minimum. The
 * batch is a payout row of its own, with no gift, holding the sums; each gift keeps its own row, now pointing at the batch, so what is
 * waiting is always the sum of the unpaid rows and a gift is in at most one batch. The rows are locked while the batch is made, so two
 * runs never batch the same gifts. The batch is then sent like any payout (SendDisbursementAction), and when it is paid every gift in it
 * is paid with it. Only a church with a verified bank account is batched; a mobile money church is paid gift by gift.
 */
final class BatchBankPayoutsAction
{
    /**
     * Takes the repositories.
     */
    public function __construct(
        private readonly DisbursementRepositoryInterface $disbursements,
        private readonly ChurchRepositoryInterface $churches,
    ) {}

    /**
     * Batches every bank church whose waiting payouts have reached the minimum, and returns how many batches were made.
     */
    public function execute(): int
    {
        $made = 0;

        foreach ($this->disbursements->churchIdsWithWaitingPayouts() as $churchId) {
            $batch = $this->batchFor($churchId);

            if ($batch !== null) {
                SendDisbursementJob::dispatch($batch->id)->onQueue('payments')->afterCommit();
                $made++;
            }
        }

        return $made;
    }

    /**
     * Makes the batch for one church, or none when it is not a bank church, its account is not verified, or the waiting payouts are
     * still under the minimum.
     */
    private function batchFor(string $churchId): ?Disbursement
    {
        $church = $this->churches->find($churchId);

        if ($church === null || $church->payoutStatus() !== 'verified' || $church->payout_method !== PayoutMethod::Bank) {
            return null;
        }

        return DB::transaction(function () use ($churchId): ?Disbursement {
            $waiting = $this->disbursements->lockWaitingFor($churchId);
            $requested = '0.00';
            $gross = '0.00';
            $fees = '0.00';

            foreach ($waiting as $payout) {
                $requested = bcadd($requested, Money::ghs($payout->requested_amount)->amount, 2);
                $gross = bcadd($gross, Money::ghs($payout->gross_amount)->amount, 2);
                $fees = bcadd($fees, Money::ghs($payout->platform_fee)->amount, 2);
            }

            if ($waiting->isEmpty() || bccomp($requested, Money::ghs((string) config('giving.bank_payout_minimum'))->amount, 2) < 0) {
                return null;
            }

            return $this->disbursements->createBatch([
                'church_id' => $churchId,
                'contribution_id' => null,
                'gross_amount' => $gross,
                'platform_fee' => $fees,
                'requested_amount' => $requested,
                'status' => DisbursementStatus::Pending,
                'provider' => PaymentProviderName::Payperlez,
                'attempts' => 0,
            ], $waiting);
        });
    }
}
