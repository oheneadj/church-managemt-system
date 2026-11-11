<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Contributions\SettleContributionAction;
use App\Contracts\PaymentProvider;
use App\DTOs\Contributions\SettleContributionDTO;
use App\Enums\ContributionStatus;
use App\Exceptions\PaymentRejectedException;
use App\Repositories\ContributionRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asks the payment provider where one gift's payment stands and settles the gift with the answer (D-069 step d3c; agreement sections
 * 10 and 15: the polling fallback for a payment that confirms later). It takes the gift's id only, and the gift's id is the reference
 * the provider knows it by. It is safe to run again: a gift that is no longer pending is skipped, and settling counts a gift once.
 * A provider that is busy is tried again; one that refuses the question is logged and the gift is left pending for the next check.
 */
class CheckContributionPaymentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    /**
     * Takes the id of the gift to check.
     */
    public function __construct(public readonly string $contributionId) {}

    /**
     * Reads the provider's answer and settles the gift with it.
     */
    public function handle(ContributionRepositoryInterface $contributions, PaymentProvider $provider, SettleContributionAction $settle): void
    {
        $gift = $contributions->find($this->contributionId);

        if ($gift === null || $gift->status !== ContributionStatus::Pending) {
            return;
        }

        try {
            $result = $provider->collectionStatus($gift->id);
        } catch (PaymentRejectedException) {
            Log::warning('The provider would not say where a gift payment stands', ['contribution_id' => $gift->id, 'church_id' => $gift->church_id]);

            return;
        }

        $settle->execute(new SettleContributionDTO($gift->id, $result));
    }

    /**
     * Logs that the checks ran out of tries. The gift stays pending and is checked again on the next round. Nothing personal goes in the line.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('A gift payment could not be checked', ['contribution_id' => $this->contributionId, 'error' => $exception::class]);
    }
}
