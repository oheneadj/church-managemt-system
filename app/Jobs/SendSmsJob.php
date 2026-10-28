<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\SmsProvider;
use App\DTOs\OutboundSms;
use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Models\MessageLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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

    public function __construct(public readonly OutboundSms $sms) {}

    public function handle(SmsProvider $provider): void
    {
        $provider->send($this->sms->phone, $this->sms->message);

        $this->record(MessageStatus::Sent);
    }

    public function failed(Throwable $exception): void
    {
        $this->record(MessageStatus::Failed);
    }

    private function record(MessageStatus $status): void
    {
        MessageLog::query()->create([
            'church_id' => $this->sms->churchId,
            'channel' => MessageChannel::Sms,
            'recipient_type' => $this->sms->recipientType,
            'recipient_id' => $this->sms->recipientId,
            'recipient_phone' => $this->sms->phone,
            'message_type' => $this->sms->messageType,
            'content' => $this->sms->logContent ?? $this->sms->message,
            'status' => $status,
            'sent_at' => $status === MessageStatus::Sent ? now() : null,
        ]);
    }
}
