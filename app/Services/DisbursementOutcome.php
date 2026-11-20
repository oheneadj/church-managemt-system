<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Payments\DisbursementResult;
use App\Enums\DisbursementStatus;
use App\Enums\ProviderPaymentStatus;
use App\Models\Disbursement;
use App\Repositories\DisbursementRepositoryInterface;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\Log;

/**
 * What a payout becomes when the provider says something about it (D-018, D-069 steps e2 and e4). Sending a payout and later asking where it
 * stands both end here, so the rules are in one place: a payout is paid only when the provider names this very attempt and the very amount
 * asked, it fails when the provider says so, and anything not final leaves it started. It never opens a transaction: the caller does.
 */
final class DisbursementOutcome
{
    /**
     * Takes the payout repository.
     */
    public function __construct(private readonly DisbursementRepositoryInterface $disbursements) {}

    /**
     * The reference and key the provider knows this attempt by: the payout's id and the attempt number. It is the same for a resend of
     * the same attempt (so a crash never pays twice) and new for each fresh attempt after a failure. The provider allows at most 100
     * letters, and an id and a number are far under that.
     */
    public function reference(Disbursement $disbursement): string
    {
        return $disbursement->id.'-'.$disbursement->attempts;
    }

    /**
     * Records the provider's answer: paid only if it names this payout and the amount asked; still working if it is not final; failed
     * if it says so.
     */
    public function apply(Disbursement $disbursement, DisbursementResult $result): Disbursement
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
    public function fail(Disbursement $disbursement, string $reason): Disbursement
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
