<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Contributions\ConfirmDisbursementAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asks the provider where one church payout stands and records the answer (D-069 step e4). It takes the payout's id only and runs on the
 * `payments` queue. It is safe to run again: the action acts only on a payout the provider has taken and not finished, and a paid payout is
 * never changed. A provider that is busy is not retried here: the payout is asked about again by the every-minute sweep.
 */
class CheckDisbursementJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    /**
     * Takes the id of the payout to check.
     */
    public function __construct(public readonly string $disbursementId) {}

    /**
     * Asks and records.
     */
    public function handle(ConfirmDisbursementAction $confirm): void
    {
        $confirm->execute($this->disbursementId);
    }

    /**
     * Logs that the check ended in an error. The payout stays as it was and the every-minute sweep asks again. Ids only.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('A church payout could not be checked', ['disbursement_id' => $this->disbursementId, 'error' => $exception::class]);
    }
}
