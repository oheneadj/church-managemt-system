<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\SmsCredits\SettleSmsCreditPurchaseAction;
use App\Contracts\PaymentProvider;
use App\DTOs\SmsCredits\SettleSmsCreditPurchaseDTO;
use App\Enums\SmsCreditPurchaseStatus;
use App\Exceptions\PaymentRejectedException;
use App\Repositories\SmsCreditPurchaseRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asks the payment provider where one purchase's payment stands and settles it (D-069 step c; agreement sections 10 and 15: the
 * polling fallback for a payment that confirms later). It takes the purchase's id only. It is safe to run again: a purchase
 * that is no longer pending is skipped, and settling adds the credits once. A provider that is busy is tried again; one that
 * refuses the question is logged and the purchase is left pending for the next check.
 */
class CheckSmsCreditPurchaseJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    /**
     * Takes the id of the purchase to check.
     */
    public function __construct(public readonly string $purchaseId) {}

    /**
     * Reads the provider's answer and settles the purchase with it.
     */
    public function handle(SmsCreditPurchaseRepositoryInterface $purchases, PaymentProvider $provider, SettleSmsCreditPurchaseAction $settle): void
    {
        $purchase = $purchases->find($this->purchaseId);

        if ($purchase === null || $purchase->status !== SmsCreditPurchaseStatus::Pending) {
            return;
        }

        try {
            $result = $provider->collectionStatus($purchase->id);
        } catch (PaymentRejectedException) {
            Log::warning('The provider would not say where an SMS credit purchase stands', ['purchase_id' => $purchase->id, 'church_id' => $purchase->church_id]);

            return;
        }

        $settle->execute(new SettleSmsCreditPurchaseDTO($purchase->id, $result));
    }

    /**
     * Logs that the checks ran out of tries. The purchase stays pending and is checked again on the next round. Nothing personal goes in the line.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('An SMS credit purchase could not be checked', ['purchase_id' => $this->purchaseId, 'error' => $exception::class]);
    }
}
