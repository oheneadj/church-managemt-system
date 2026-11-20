<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\PaymentProvider;
use App\Enums\ContributionFailureReason;
use App\Enums\ContributionStatus;
use App\Enums\MomoNetwork;
use App\Exceptions\PaymentRejectedException;
use App\Repositories\ContributionRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asks the payment provider to collect for one pending gift (D-069 step d3): the MoMo prompt goes to the giver's phone. It runs on
 * a worker because a request never waits on a provider. The gift's id is the provider's client reference, so running again after a
 * crash or a retry never charges twice. The job is encrypted in the queue because it carries the giver's phone number. If the
 * provider refuses for good the gift becomes failed and nothing is charged; if it is busy the job is tried again; a gift that
 * is no longer pending is left alone. The provider's answer about the payment itself is read by the check that settles pending
 * gifts, so an instant answer is not lost.
 */
class StartContributionPaymentJob implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    /**
     * Takes the gift to collect for and the wallet to ask.
     */
    public function __construct(public readonly string $contributionId, public readonly string $phone, public readonly string $network) {}

    /**
     * Starts the collection and keeps the provider's reference.
     */
    public function handle(ContributionRepositoryInterface $contributions, PaymentProvider $provider): void
    {
        $gift = $contributions->find($this->contributionId);

        if ($gift === null || $gift->status !== ContributionStatus::Pending || $gift->payperlez_reference !== null || $gift->total_paid === null) {
            return;
        }

        try {
            $result = $provider->collect($this->phone, $gift->total_paid, $gift->id, MomoNetwork::from($this->network));
        } catch (PaymentRejectedException) {
            Log::warning('The provider refused to start a gift payment', ['contribution_id' => $gift->id, 'church_id' => $gift->church_id]);
            $gift->status = ContributionStatus::Failed;
            $gift->failure_reason = ContributionFailureReason::Refused;
            $contributions->save($gift);

            return;
        }

        if ($result->reference !== '') {
            $gift->payperlez_reference = $result->reference;
            $contributions->save($gift);
        }
    }

    /**
     * Logs that the tries ran out. The gift stays pending, so the check on pending gifts finds out whether the provider ever
     * received it. Nothing personal goes in the line.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('A gift payment could not be started', ['contribution_id' => $this->contributionId, 'error' => $exception::class]);
    }
}
