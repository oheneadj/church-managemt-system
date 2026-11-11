<?php

declare(strict_types=1);

namespace App\Actions\Contributions;

use App\DTOs\Contributions\SettleContributionDTO;
use App\Enums\AuditEvent;
use App\Enums\ContributionFailureReason;
use App\Enums\ContributionStatus;
use App\Enums\DisbursementStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProviderName;
use App\Enums\ProviderPaymentStatus;
use App\Events\ContributionReceived;
use App\Models\Contribution;
use App\Models\User;
use App\Repositories\ContributionRepositoryInterface;
use App\Repositories\DisbursementRepositoryInterface;
use App\Services\AuditLogger;
use App\Services\GivingFeeCalculator;
use App\Services\PledgeProgressService;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns the provider's final word on a mobile money gift into its outcome (D-053, D-069 step d3c). A paid gift becomes completed, so
 * it is counted everywhere, pays its pledge, gets its receipt and records the payout the church is owed, once however many times this runs: the gift row is locked and only
 * a pending gift is acted on. A failed payment makes it failed and nothing follows. Anything the provider has not finished with is
 * left pending. A gift that expired unpaid is still counted if the provider says it was paid within the grace period (a late approval).
 * A gift is never completed unless the provider confirms the very payment and the very total that was asked for.
 */
final class SettleContributionAction
{
    /**
     * Takes the repository, the pledge progress service and the change-history writer.
     */
    public function __construct(
        private readonly ContributionRepositoryInterface $contributions,
        private readonly PledgeProgressService $pledgeProgress,
        private readonly AuditLogger $audit,
        private readonly DisbursementRepositoryInterface $disbursements,
        private readonly GivingFeeCalculator $fees,
    ) {}

    /**
     * Applies the provider's answer and returns the gift as it now stands, or null when there is no such gift.
     */
    public function execute(SettleContributionDTO $dto): ?Contribution
    {
        return DB::transaction(function () use ($dto): ?Contribution {
            $gift = $this->contributions->lockById($dto->contributionId);

            if ($gift === null || $gift->payment_method === PaymentMethod::Cash || ! $this->canBeSettled($gift)) {
                $this->reportPaidAfterFailure($gift, $dto);

                return $gift;
            }

            match ($dto->result->status) {
                ProviderPaymentStatus::Succeeded => $this->complete($gift, $dto),
                ProviderPaymentStatus::Failed => $this->fail($gift, $dto),
                ProviderPaymentStatus::Pending, ProviderPaymentStatus::Unknown => null,
            };

            return $gift;
        });
    }

    /**
     * Whether the provider's word can still change this gift: a pending one, or one that expired unpaid and is still within the
     * grace for a late approval (a giver may approve after we stopped waiting, and their money has moved).
     */
    private function canBeSettled(Contribution $gift): bool
    {
        if ($gift->status === ContributionStatus::Pending) {
            return true;
        }

        return $gift->status === ContributionStatus::Failed
            && $gift->failure_reason === ContributionFailureReason::Expired
            && $gift->created_at !== null
            && $gift->created_at->greaterThanOrEqualTo(now()->subHours((int) config('giving.late_payment_grace_hours')));
    }

    /**
     * A gift marked failed that the provider now says was paid is never counted quietly, because the giver's money has moved and a
     * person must look into it. It is logged with ids only.
     */
    private function reportPaidAfterFailure(?Contribution $gift, SettleContributionDTO $dto): void
    {
        if ($gift?->status === ContributionStatus::Failed && $dto->result->status === ProviderPaymentStatus::Succeeded) {
            Log::error('A gift marked failed was reported paid by the provider', ['contribution_id' => $gift->id, 'church_id' => $gift->church_id]);
        }
    }

    /**
     * Counts the gift, unless the provider's answer is not about this payment or this total.
     */
    private function complete(Contribution $gift, SettleContributionDTO $dto): void
    {
        $result = $dto->result;

        if ($result->clientReference !== $gift->id || bccomp(Money::ghs($result->amount)->amount, Money::ghs((string) $gift->total_paid)->amount, 2) !== 0) {
            Log::error('A paid gift does not match what the provider reported', ['contribution_id' => $gift->id, 'church_id' => $gift->church_id]);

            return;
        }

        $gift->status = ContributionStatus::Completed;
        $gift->failure_reason = null;
        $gift->payperlez_reference = $result->reference !== '' ? $result->reference : $gift->payperlez_reference;
        $this->contributions->save($gift);

        $this->recordInHistory($gift);
        $this->owePayout($gift);

        if ($gift->pledge_id !== null) {
            $this->pledgeProgress->refresh($gift->pledge_id);
        }

        ContributionReceived::dispatch($gift);
    }

    /**
     * Records the payout the church is owed for this gift (D-018): the gift less the platform's fee, with the fee actually charged kept
     * on the row, so a later change to the rate never alters it. The giver paid the collection fee on top, so the church is owed the
     * whole gift less only our fee. Sending it is the payout job's work; a bank church's payout waits for its minimum (D-072).
     */
    private function owePayout(Contribution $gift): void
    {
        $amount = Money::ghs($gift->amount);

        $this->disbursements->createForGift([
            'church_id' => $gift->church_id,
            'contribution_id' => $gift->id,
            'gross_amount' => $amount->amount,
            'platform_fee' => $this->fees->platformFee($amount)->amount,
            'requested_amount' => $this->fees->requestedPayout($amount)->amount,
            'status' => DisbursementStatus::Pending,
            'provider' => PaymentProviderName::Payperlez,
            'attempts' => 0,
        ]);
    }

    /**
     * Records that the payment did not go through. It was never counted, so nothing is taken back.
     */
    private function fail(Contribution $gift, SettleContributionDTO $dto): void
    {
        if ($gift->status !== ContributionStatus::Pending) {
            return;
        }

        $gift->status = ContributionStatus::Failed;
        $gift->failure_reason = ContributionFailureReason::Declined;
        $gift->payperlez_reference = $dto->result->reference !== '' ? $dto->result->reference : $gift->payperlez_reference;
        $this->contributions->save($gift);
    }

    /**
     * Writes the change-history line for a gift a staff member started, now that it counts, as them. A gift given on the public page
     * has no staff member to credit, so it has no line.
     */
    private function recordInHistory(Contribution $gift): void
    {
        $actor = $gift->recorded_by === null ? null : User::query()->withoutGlobalScopes()->find($gift->recorded_by);

        if ($actor === null) {
            return;
        }

        $this->audit->record($gift, AuditEvent::Created, $actor, [], [
            'amount' => $gift->amount,
            'fund_type_id' => $gift->fund_type_id,
            'member_id' => $gift->member_id,
            'payment_method' => $gift->payment_method->value,
            'pledge_id' => $gift->pledge_id,
        ]);
    }
}
