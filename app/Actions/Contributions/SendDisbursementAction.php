<?php

declare(strict_types=1);

namespace App\Actions\Contributions;

use App\Contracts\DisbursementProvider;
use App\DTOs\Payments\DisbursementResult;
use App\Enums\DisbursementStatus;
use App\Enums\PayoutMethod;
use App\Enums\ProviderPaymentStatus;
use App\Exceptions\PaymentRejectedException;
use App\Models\Disbursement;
use App\Repositories\ChurchRepositoryInterface;
use App\Repositories\DisbursementRepositoryInterface;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sends a church the money it is owed for one gift (D-018, D-069 step e2). It pays the requested amount to the church's verified mobile
 * money wallet, with the payout's id and attempt number as the provider's reference and key, so asking twice for the same attempt never
 * pays twice, and a retry after a failure is a fresh attempt the provider will not answer with the old failure. It only sends when
 * the provider will still hold its minimum balance afterwards; otherwise the payout stays pending and is tried on the next round. The
 * payout is claimed under a lock and marked as started before the provider is called, so two workers never send the same one, and a
 * payout that crashed mid-call is found again and asked with the same key. A refusal for good marks it failed with the reason; failed
 * payouts are tried again, up to the attempts allowed, and then an alert is raised, because until it succeeds the church's money is
 * sitting with the platform. A payout is only marked paid when the provider names this very payout and the very amount asked.
 * A bank church's own small payouts are not sent here: they wait to be put into a batch of at least the bank minimum (D-072), and the
 * batch is sent like any other payout; when it is paid, every gift in it is paid with it. A payout that is inside a batch is never sent on
 * its own.
 */
final class SendDisbursementAction
{
    /**
     * Takes the repositories and the provider.
     */
    public function __construct(
        private readonly DisbursementRepositoryInterface $disbursements,
        private readonly ChurchRepositoryInterface $churches,
        private readonly DisbursementProvider $provider,
    ) {}

    /**
     * Sends the payout if it is due and can be sent, and returns it as it now stands, or null when there is no such payout.
     */
    public function execute(string $disbursementId): ?Disbursement
    {
        $disbursement = $this->disbursements->find($disbursementId);

        if ($disbursement === null || ! $this->isDue($disbursement)) {
            return $disbursement;
        }

        $church = $this->churches->find($disbursement->church_id);
        $account = $church?->payoutAccount();

        if ($church === null || $church->payoutStatus() !== 'verified' || $account === null || ! $this->mayBeSentTo($disbursement, $church->payout_method)) {
            return $disbursement;
        }

        if (! $this->balanceCovers($disbursement)) {
            return $disbursement;
        }

        $claimed = $this->claim($disbursement->id);

        if ($claimed === null) {
            return $disbursement;
        }

        try {
            $result = $this->provider->disburse($account, $claimed->requested_amount, $this->reference($claimed));
        } catch (PaymentRejectedException $e) {
            return $this->fail($claimed, $e->getMessage());
        }

        return $this->record($claimed, $result);
    }

    /**
     * Whether this payout may go to the church's account now. A gift's own payout goes only to a mobile money wallet: a bank church's
     * small payouts wait for a batch of at least the bank minimum (D-072). A batch has no gift of its own and goes to whatever account the
     * church has.
     */
    private function mayBeSentTo(Disbursement $disbursement, ?PayoutMethod $method): bool
    {
        return $disbursement->contribution_id === null || $method === PayoutMethod::Momo;
    }

    /**
     * Whether this payout is due now: pending; failed with attempts left and left alone long enough; or started but never given a
     * provider reference and left for long enough.
     */
    private function isDue(Disbursement $disbursement): bool
    {
        $changed = $disbursement->updated_at;

        if ($disbursement->batch_id !== null) {
            return false;
        }

        return match ($disbursement->status) {
            DisbursementStatus::Pending => true,
            DisbursementStatus::Failed => $disbursement->attempts < (int) config('giving.payout_max_attempts')
                && $changed !== null && $changed->lessThanOrEqualTo(now()->subMinutes((int) config('giving.payout_retry_after_minutes'))),
            DisbursementStatus::Processing => $disbursement->provider_reference === null
                && $changed !== null && $changed->lessThanOrEqualTo(now()->subMinutes((int) config('giving.payout_stuck_after_minutes'))),
            DisbursementStatus::Paid => false,
        };
    }

    /**
     * Whether the provider will still hold its minimum balance after this payout. A balance the provider will not give is no answer:
     * the payout waits.
     */
    private function balanceCovers(Disbursement $disbursement): bool
    {
        try {
            $available = Money::ghs($this->provider->availableBalance())->amount;
        } catch (PaymentRejectedException) {
            Log::warning('The provider would not give its balance, so a payout waits', ['disbursement_id' => $disbursement->id, 'church_id' => $disbursement->church_id]);

            return false;
        }

        $left = bcsub($available, Money::ghs($disbursement->requested_amount)->amount, 2);

        return bccomp($left, Money::ghs((string) config('giving.payout_minimum_balance'))->amount, 2) >= 0;
    }

    /**
     * The reference and key the provider knows this attempt by: the payout's id and the attempt number. It is the same for a resend of
     * the same attempt (so a crash never pays twice) and new for each fresh attempt after a failure. The provider allows at most 100
     * letters, and an id and a number are far under that.
     */
    private function reference(Disbursement $disbursement): string
    {
        return $disbursement->id.'-'.$disbursement->attempts;
    }

    /**
     * Takes the payout under a lock and marks it as started, counting the attempt, before the provider is called. Returns null when it
     * is no longer due, because another worker took it.
     */
    private function claim(string $disbursementId): ?Disbursement
    {
        return DB::transaction(function () use ($disbursementId): ?Disbursement {
            $locked = $this->disbursements->lockById($disbursementId);

            if ($locked === null || ! $this->isDue($locked)) {
                return null;
            }

            // A payout that crashed mid-call is sent again under the same attempt, so the provider's key is the same and it cannot pay twice. A
            // payout that was refused or failed is a new attempt, so it gets a new reference: the provider would only repeat the old answer.
            if ($locked->status !== DisbursementStatus::Processing) {
                $locked->attempts++;
            }

            $locked->status = DisbursementStatus::Processing;
            $locked->failure_reason = null;

            return $this->disbursements->save($locked);
        });
    }

    /**
     * Records the provider's answer: paid only if it names this payout and the amount asked; still working if it is not final; failed
     * if it says so.
     */
    private function record(Disbursement $disbursement, DisbursementResult $result): Disbursement
    {
        $disbursement->provider_reference = $result->reference !== '' ? $result->reference : $disbursement->provider_reference;

        return match ($result->status) {
            ProviderPaymentStatus::Succeeded => $this->markPaid($disbursement, $result),
            ProviderPaymentStatus::Failed => $this->fail($disbursement, 'The provider said the payout did not go through.'),
            ProviderPaymentStatus::Pending, ProviderPaymentStatus::Unknown => $this->disbursements->save($disbursement),
        };
    }

    /**
     * Marks the payout paid, with what the provider charged and what the church received, unless the answer is not about this payout
     * and this amount, in which case it is left started for a person to look into.
     */
    private function markPaid(Disbursement $disbursement, DisbursementResult $result): Disbursement
    {
        if ($result->clientReference !== $this->reference($disbursement) || bccomp(Money::ghs($result->amount)->amount, Money::ghs($disbursement->requested_amount)->amount, 2) !== 0) {
            Log::error('A paid payout does not match what the provider reported', ['disbursement_id' => $disbursement->id, 'church_id' => $disbursement->church_id]);

            return $this->disbursements->save($disbursement);
        }

        $disbursement->status = DisbursementStatus::Paid;
        $disbursement->provider_fee = $result->fee === null ? null : Money::ghs($result->fee)->amount;
        $disbursement->net_amount = $result->received !== null
            ? Money::ghs($result->received)->amount
            : ($result->fee === null ? null : bcsub(Money::ghs($disbursement->requested_amount)->amount, Money::ghs($result->fee)->amount, 2));
        $disbursement->paid_at = now();
        $saved = $this->disbursements->save($disbursement);

        if ($saved->contribution_id === null) {
            $this->disbursements->markBatchPaid($saved->id, now());
        }

        return $saved;
    }

    /**
     * Marks the payout failed with the reason. When the attempts are used up an alert is raised (ids only), because the church's money is
     * still with the platform.
     */
    private function fail(Disbursement $disbursement, string $reason): Disbursement
    {
        $disbursement->status = DisbursementStatus::Failed;
        $disbursement->failure_reason = mb_substr($reason, 0, 255);
        $saved = $this->disbursements->save($disbursement);

        if ($saved->attempts >= (int) config('giving.payout_max_attempts')) {
            Log::critical('A church payout failed for good and needs a person', ['disbursement_id' => $saved->id, 'church_id' => $saved->church_id, 'attempts' => $saved->attempts]);
        }

        return $saved;
    }
}
