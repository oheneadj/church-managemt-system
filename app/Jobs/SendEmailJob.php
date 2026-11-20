<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DTOs\OutboundEmail;
use App\DTOs\ProviderReceipt;
use App\Enums\MessageStatus;
use App\Repositories\MessageLogRepositoryInterface;
use App\Services\CircuitBreaker;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends one email through the configured mailer (Resend by SMTP, `.env` only, D-014) and records the outcome in
 * message_logs. It runs on a worker, so the church and the log line travel with it. Like the SMS job it stands behind a
 * circuit breaker, can be paused by hand (`email:pause`), and never writes an address or a code to the application log.
 * Email is at-least-once: a worker that dies after the provider accepted a message but before the log line says "sent"
 * could send it twice; the sent check below makes that the only way a repeat can happen.
 */
class SendEmailJob implements ShouldQueue
{
    use Queueable;

    /** Real failures of one email that fail it for good. Emails held back by an open circuit do not count. */
    public int $tries = 3;

    public int $maxExceptions = 3;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    /** The name of this provider's circuit breaker. */
    private const CIRCUIT = 'email';

    /**
     * Takes the email and the id of the message log line made when it was queued.
     */
    public function __construct(public readonly OutboundEmail $email, public readonly string $messageLogId) {}

    /**
     * Keeps trying for the retry window while the circuit is open or the mail provider is failing. Because a retry time is
     * set, held-back emails are not counted as attempts; only real failures are (maxExceptions).
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(config('resilience.email.retry_minutes'));
    }

    /**
     * Sends the email unless it was already sent, and records it as sent with the provider's message id. While the
     * circuit is open or email is paused, the email waits and is put back on the queue. A failed send counts against the
     * circuit and is thrown, so the queue retries it.
     */
    public function handle(MessageLogRepositoryInterface $logs, CircuitBreaker $circuit): void
    {
        $log = $logs->find($this->messageLogId);

        if ($log === null || $log->status === MessageStatus::Sent) {
            return;
        }

        if (! $circuit->allows(self::CIRCUIT)) {
            $this->release(config('resilience.email.release_seconds'));

            return;
        }

        try {
            $sent = Mail::to($this->email->email)->send($this->email->mailable);
        } catch (Throwable $temporary) {
            $circuit->recordFailure(self::CIRCUIT);

            throw $temporary;
        }

        $circuit->recordSuccess(self::CIRCUIT);
        $logs->markSent($log, new ProviderReceipt($sent?->getMessageId()));
    }

    /**
     * Records the email as failed once every retry is used up, and reports it so someone is told: a code that never
     * arrives locks a person out (the email runbook says what to do).
     */
    public function failed(Throwable $exception): void
    {
        $logs = app(MessageLogRepositoryInterface::class);
        $log = $logs->find($this->messageLogId);

        if ($log !== null) {
            $logs->markFailed($log);
        }

        report($exception);
    }
}
