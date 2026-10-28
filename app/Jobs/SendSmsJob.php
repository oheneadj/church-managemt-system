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

    public int $tries = 3;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [5, 20, 60];

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
     * Sends the text through the active provider and records it as sent. A message the provider refuses for good
     * is not retried: the job fails at once and failed() records it.
     */
    public function handle(SmsProvider $provider, MessageLogRepositoryInterface $logs): void
    {
        try {
            $receipt = $provider->send($this->sms, $this->idempotencyKey);
        } catch (SmsRejectedException $rejected) {
            $this->fail($rejected);

            return;
        }

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
