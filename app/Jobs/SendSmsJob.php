<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\SmsProvider;
use App\DTOs\OutboundSms;
use App\DTOs\SmsReceipt;
use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Exceptions\SmsRejectedException;
use App\Repositories\MessageLogRepositoryInterface;
use App\Services\CircuitBreaker;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends one SMS through the configured provider and records the outcome in message_logs.
 * Runs on a worker, so the church is passed in explicitly rather than read from the request.
 */
class SendSmsJob implements ShouldQueue
{
    use Queueable;

    /** Real failures of one message that fail it for good. Messages held back by an open circuit do not count. */
    public int $tries = 3;

    public int $maxExceptions = 3;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [5, 20, 60];

    /** The name of this provider's circuit breaker. */
    private const CIRCUIT = 'sms';

    /** The key that makes every retry of this one message look the same to the provider, so it is never sent twice. */
    public readonly string $idempotencyKey;

    /**
     * Takes the SMS and makes its retry key. The key is saved with the job, so retries reuse it.
     */
    public function __construct(public readonly OutboundSms $sms)
    {
        $this->idempotencyKey = 'sms-'.Str::ulid();
    }

    /**
     * Keeps trying for the retry window while the circuit is open or the provider is failing. Because a retry time
     * is set, held-back messages are not counted as attempts; only real failures are (maxExceptions).
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(config('resilience.sms.retry_minutes'));
    }

    /**
     * Sends the text through the active provider and records it as sent. While the provider's circuit is open or
     * sending is paused, the message waits and is put back on the queue. A message the provider refuses for good is
     * not retried: the job fails at once and failed() records it. A temporary failure counts against the circuit.
     */
    public function handle(SmsProvider $provider, MessageLogRepositoryInterface $logs, CircuitBreaker $circuit): void
    {
        if (! $circuit->allows(self::CIRCUIT)) {
            $this->release(config('resilience.sms.release_seconds'));

            return;
        }

        try {
            $receipt = $provider->send($this->sms, $this->idempotencyKey);
        } catch (SmsRejectedException $rejected) {
            // The provider answered, so it is up: this message is the problem, not the provider.
            $circuit->recordSuccess(self::CIRCUIT);
            $this->fail($rejected);

            return;
        } catch (Throwable $temporary) {
            $circuit->recordFailure(self::CIRCUIT);

            throw $temporary;
        }

        $circuit->recordSuccess(self::CIRCUIT);
        $this->record($logs, MessageStatus::Sent, $receipt);
    }

    /**
     * Records the text as failed once every retry is used up.
     */
    public function failed(Throwable $exception): void
    {
        $this->record(app(MessageLogRepositoryInterface::class), MessageStatus::Failed);
    }

    /**
     * Writes one line to the message log with the given outcome.
     */
    private function record(MessageLogRepositoryInterface $logs, MessageStatus $status, ?SmsReceipt $receipt = null): void
    {
        $logs->create([
            'church_id' => $this->sms->churchId,
            'channel' => MessageChannel::Sms,
            'recipient_type' => $this->sms->recipientType,
            'recipient_id' => $this->sms->recipientId,
            'recipient_phone' => $this->sms->phone,
            'message_type' => $this->sms->messageType,
            'content' => $this->sms->logContent ?? $this->sms->message,
            'status' => $status,
            'provider_reference' => $receipt?->reference,
            'provider_response' => $receipt?->response,
            'sent_at' => $status === MessageStatus::Sent ? now() : null,
        ]);
    }
}
