<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\SmsCredits\SettleSmsCreditPurchaseAction;
use App\Contracts\PaymentProvider;
use App\DTOs\Payments\CollectionResult;
use App\DTOs\SmsCredits\SettleSmsCreditPurchaseDTO;
use App\Enums\ProviderPaymentStatus;
use App\Enums\SmsCreditPurchaseStatus;
use App\Exceptions\PaymentRejectedException;
use App\Repositories\SmsCreditPurchaseRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asks the payment provider to collect for one purchase (D-069, story 5.4): the MoMo prompt goes to the buyer's phone. It runs on
 * a worker because a request never waits on a provider. The purchase's id is the provider's client reference, so running again
 * after a crash or a retry never charges twice. If the provider refuses for good the purchase fails and nothing is charged; if it
 * is busy the job is tried again; whatever it answers is settled the same way as a later check, so an instant answer is not lost.
 */
class StartSmsCreditPurchaseJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    /**
     * Takes the id of the purchase to collect for.
     */
    public function __construct(public readonly string $purchaseId) {}

    /**
     * Starts the collection and settles the purchase with the provider's first answer.
     */
    public function handle(SmsCreditPurchaseRepositoryInterface $purchases, PaymentProvider $provider, SettleSmsCreditPurchaseAction $settle): void
    {
        $purchase = $purchases->find($this->purchaseId);

        if ($purchase === null || $purchase->status !== SmsCreditPurchaseStatus::Pending || $purchase->provider_reference !== null) {
            return;
        }

        try {
            $result = $provider->collect($purchase->phone, $purchase->amount, $purchase->id, $purchase->network);
        } catch (PaymentRejectedException) {
            Log::warning('The provider refused to start an SMS credit purchase', ['purchase_id' => $purchase->id, 'church_id' => $purchase->church_id]);
            $result = new CollectionResult('', $purchase->id, ProviderPaymentStatus::Failed, $purchase->amount, null, null);
        }

        $settled = $settle->execute(new SettleSmsCreditPurchaseDTO($purchase->id, $result));

        if ($settled?->status === SmsCreditPurchaseStatus::Pending && $result->reference !== '') {
            $settled->provider_reference = $result->reference;
            $purchases->save($settled);
        }
    }

    /**
     * Logs that the tries ran out. The purchase stays pending, so the every-minute check finds out whether the provider ever
     * received it. Nothing personal goes in the line.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('An SMS credit purchase could not be started', ['purchase_id' => $this->purchaseId, 'error' => $exception::class]);
    }
}
