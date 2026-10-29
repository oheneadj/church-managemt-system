<?php

declare(strict_types=1);

namespace App\Jobs\Middleware;

use App\Actions\SmsCredits\ReserveSmsCreditsAction;
use App\DTOs\SmsCredits\SmsCreditChange;
use App\Exceptions\InsufficientSmsCreditsException;
use App\Jobs\SendSmsJob;
use Closure;

/**
 * Runs before a church-paid text is sent and takes its credits (D-051). A text that is not charged to a church (a
 * sign-in or check-in code) goes straight through. When the church cannot pay, the job fails at once and nothing is
 * sent. It runs on every attempt; paying twice is impossible because a message already charged is not charged again.
 */
class ReservesSmsCredits
{
    /**
     * Takes the action that reserves credits.
     */
    public function __construct(private readonly ReserveSmsCreditsAction $reserve) {}

    /**
     * Takes the credits, then lets the job run; or fails the job when they cannot be taken.
     */
    public function handle(SendSmsJob $job, Closure $next): void
    {
        $sms = $job->sms;

        if ($sms->creditCost <= 0 || $sms->churchId === null || $sms->messageLogId === null) {
            $next($job);

            return;
        }

        if (! $this->reserve->execute(new SmsCreditChange($sms->churchId, $sms->creditCost, $sms->messageLogId))) {
            $job->fail(new InsufficientSmsCreditsException('The church has too few SMS credits for this text.'));

            return;
        }

        $next($job);
    }
}
