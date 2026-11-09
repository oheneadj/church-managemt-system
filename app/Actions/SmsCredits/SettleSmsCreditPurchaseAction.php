<?php

declare(strict_types=1);

namespace App\Actions\SmsCredits;

use App\DTOs\SmsCredits\SettleSmsCreditPurchaseDTO;
use App\Enums\CreditLedgerType;
use App\Enums\ProviderPaymentStatus;
use App\Enums\SmsCreditPurchaseStatus;
use App\Models\SmsCreditPurchase;
use App\Repositories\ChurchRepositoryInterface;
use App\Repositories\SmsCreditLedgerRepositoryInterface;
use App\Repositories\SmsCreditPurchaseRepositoryInterface;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns the provider's final word on a purchase into its outcome (D-069 step c). A paid purchase adds its credits to the church
 * and writes the ledger line, once, however many times this runs: the purchase row and the church row are locked, only a pending
 * purchase is acted on, and the ledger holds one `topup` line for each purchase. A failed payment adds nothing. Anything the
 * provider has not finished with is left pending. Credits are never added unless the provider confirms the very amount asked for.
 */
final class SettleSmsCreditPurchaseAction
{
    /** Kept on a purchase whose payment the provider says did not go through. */
    public const FAILURE_REASON = 'The payment was not completed.';

    /**
     * Takes the purchase, church and ledger repositories.
     */
    public function __construct(
        private readonly SmsCreditPurchaseRepositoryInterface $purchases,
        private readonly ChurchRepositoryInterface $churches,
        private readonly SmsCreditLedgerRepositoryInterface $ledger,
    ) {}

    /**
     * Applies the provider's answer and returns the purchase as it now stands, or null when there is no such purchase.
     */
    public function execute(SettleSmsCreditPurchaseDTO $dto): ?SmsCreditPurchase
    {
        return DB::transaction(function () use ($dto): ?SmsCreditPurchase {
            $purchase = $this->purchases->lock($dto->purchaseId);

            if ($purchase === null || $purchase->status !== SmsCreditPurchaseStatus::Pending) {
                return $purchase;
            }

            match ($dto->result->status) {
                ProviderPaymentStatus::Succeeded => $this->complete($purchase, $dto),
                ProviderPaymentStatus::Failed => $this->fail($purchase, $dto),
                ProviderPaymentStatus::Pending, ProviderPaymentStatus::Unknown => null,
            };

            return $purchase;
        });
    }

    /**
     * Adds the credits and writes the ledger line, unless the provider's answer is not about this payment or this amount.
     */
    private function complete(SmsCreditPurchase $purchase, SettleSmsCreditPurchaseDTO $dto): void
    {
        $result = $dto->result;

        if ($result->clientReference !== $purchase->id || bccomp(Money::ghs($result->amount)->amount, $purchase->amount, 2) !== 0) {
            Log::error('A paid SMS credit purchase does not match what the provider reported', ['purchase_id' => $purchase->id, 'church_id' => $purchase->church_id]);

            return;
        }

        $church = $this->churches->lock($purchase->church_id);
        $church->sms_credits += $purchase->credits;
        $this->churches->save($church);
        $this->ledger->append([
            'church_id' => $church->id,
            'type' => CreditLedgerType::Topup,
            'credits' => $purchase->credits,
            'balance_after' => $church->sms_credits,
            'reference_id' => $purchase->id,
            'note' => 'Bought SMS credits',
        ]);

        $purchase->status = SmsCreditPurchaseStatus::Completed;
        $purchase->provider_reference = $result->reference;
        $purchase->provider_fee = $result->fee === null ? null : Money::ghs($result->fee)->amount;
        $purchase->completed_at = now();
        $this->purchases->save($purchase);
    }

    /**
     * Records that the payment did not go through. Nothing was added, so nothing is taken back.
     */
    private function fail(SmsCreditPurchase $purchase, SettleSmsCreditPurchaseDTO $dto): void
    {
        $purchase->status = SmsCreditPurchaseStatus::Failed;
        $purchase->provider_reference = $dto->result->reference;
        $purchase->failure_reason = self::FAILURE_REASON;
        $this->purchases->save($purchase);
    }
}
