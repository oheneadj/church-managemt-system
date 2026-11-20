<?php

declare(strict_types=1);

namespace App\Actions\SmsCredits;

use App\DTOs\SmsCredits\StartSmsCreditPurchaseDTO;
use App\Enums\SmsCreditPurchaseStatus;
use App\Jobs\StartSmsCreditPurchaseJob;
use App\Models\SmsCreditPurchase;
use App\Repositories\SmsCreditPurchaseRepositoryInterface;
use App\Repositories\SmsPackageRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Starts a church's purchase of an SMS credit package (D-069, story 5.4). It records the purchase as pending, with the credits and
 * price copied from the package, and queues the call to the payment provider: the request never waits on the provider (agreement
 * section 15). Nothing is charged here and no credits are added: that happens when the provider confirms
 * (SettleSmsCreditPurchaseAction). The purchase's id is the reference the provider sees, so a retry never charges twice.
 */
final class StartSmsCreditPurchaseAction
{
    /**
     * Takes the package and purchase repositories.
     */
    public function __construct(
        private readonly SmsPackageRepositoryInterface $packages,
        private readonly SmsCreditPurchaseRepositoryInterface $purchases,
    ) {}

    /**
     * Records the pending purchase, queues the payment once it is saved, and returns it.
     *
     * @throws ValidationException when the package was switched off since it was validated
     */
    public function execute(StartSmsCreditPurchaseDTO $dto): SmsCreditPurchase
    {
        $package = $this->packages->findActive($dto->packageId)
            ?? throw ValidationException::withMessages(['sms_package_id' => 'That package is not on sale.']);

        $purchase = DB::transaction(fn (): SmsCreditPurchase => $this->purchases->create([
            'church_id' => $dto->church->id,
            'sms_package_id' => $package->id,
            'user_id' => $dto->actor->id,
            'credits' => $package->credits,
            'amount' => $package->price,
            'network' => $dto->network,
            'phone' => $dto->phone,
            'status' => SmsCreditPurchaseStatus::Pending,
        ]));

        StartSmsCreditPurchaseJob::dispatch($purchase->id)->onQueue('payments');

        return $purchase;
    }
}
