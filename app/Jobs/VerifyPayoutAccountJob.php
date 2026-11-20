<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\DisbursementProvider;
use App\Exceptions\PaymentRejectedException;
use App\Repositories\ChurchRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asks the provider to name the church's payout account (story 4.1, D-069 step d2). It runs on a worker because a request never
 * waits on a provider. When the provider names the account the name is kept and the account is verified; when it refuses, the
 * reason is kept for the church to see; when it is busy the job is tried again. It carries the number it was queued for, so a
 * check that finishes after the church has already changed the account is thrown away and never marks the new one.
 */
class VerifyPayoutAccountJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    /**
     * Takes the church and the account number this check is for.
     */
    public function __construct(public readonly string $churchId, public readonly string $accountNumber) {}

    /**
     * Checks the account and records the answer.
     */
    public function handle(ChurchRepositoryInterface $churches, DisbursementProvider $provider): void
    {
        $church = $churches->find($this->churchId);
        $account = $church?->payoutAccount();

        if ($church === null || $account === null || $church->payout_verified_at !== null || $church->payout_account_number !== $this->accountNumber) {
            return;
        }

        $name = null;
        $reason = 'The provider could not name the account.';

        try {
            $name = $provider->verifyAccount($account);
        } catch (PaymentRejectedException $e) {
            $reason = $e->getMessage();
        }

        $church = $churches->lock($this->churchId);

        if ($church->payout_account_number !== $this->accountNumber || $church->payout_verified_at !== null) {
            return;
        }

        if ($name === null) {
            $church->payout_check_error = mb_substr($reason, 0, 255);
        } else {
            $church->payout_account_name = $name;
            $church->payout_verified_at = now();
            $church->payout_check_error = null;
        }

        $churches->save($church);
    }

    /**
     * Logs that the tries ran out. The account stays "checking" and the church can save it again. No account detail is logged.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('A payout account could not be checked', ['church_id' => $this->churchId, 'error' => $exception::class]);
    }
}
