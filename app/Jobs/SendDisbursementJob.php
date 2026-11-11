<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Contributions\SendDisbursementAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one church payout (D-018, D-069 step e2). It takes the payout's id only and runs on the `payments` queue. It is safe to run
 * again: the action sends only a payout that is due, claims it under a lock, and the provider is given the payout's own id as its key, so
 * a second run never pays twice. A provider that is busy is not retried here: the payout is picked up again by the every-minute sweep.
 */
class SendDisbursementJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 90;

    /**
     * Takes the id of the payout to send.
     */
    public function __construct(public readonly string $disbursementId) {}

    /**
     * Sends the payout if it is due.
     */
    public function handle(SendDisbursementAction $send): void
    {
        $send->execute($this->disbursementId);
    }

    /**
     * Logs that the job ended in an error. The payout stays as it was and the every-minute sweep finds it again. Ids only.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('A church payout could not be sent', ['disbursement_id' => $this->disbursementId, 'error' => $exception::class]);
    }
}
