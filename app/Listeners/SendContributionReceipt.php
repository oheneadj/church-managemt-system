<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Contributions\SendContributionReceiptAction;
use App\Events\ContributionReceived;
use App\Services\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

/**
 * Sends the receipt text for a gift that was recorded (D-051). It is queued, so recording a gift never waits for it
 * (docs/09, Async First), and it waits for the commit. It runs on a worker with no church in context, so it sets the
 * gift's church while it works.
 */
class SendContributionReceipt implements ShouldQueue
{
    public string $queue = 'notifications';

    public bool $afterCommit = true;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    /**
     * Takes the action that sends the receipt and the tenant helper.
     */
    public function __construct(private readonly SendContributionReceiptAction $receipt, private readonly TenantContext $tenant) {}

    /**
     * Sends the receipt, as the gift's church.
     */
    public function handle(ContributionReceived $event): void
    {
        $contribution = $event->contribution;
        $church = $contribution->church ?? throw new LogicException('A gift always belongs to a church.');

        $this->tenant->run($church, fn () => $this->receipt->execute($contribution));
    }

    /**
     * Logs that a receipt could not be sent. The gift is safe; only the text is missing. Only ids are logged.
     */
    public function failed(ContributionReceived $event, Throwable $exception): void
    {
        Log::error('A gift receipt could not be sent', ['contribution_id' => $event->contribution->id, 'error' => $exception::class]);
    }
}
