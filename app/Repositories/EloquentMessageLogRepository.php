<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\SmsReceipt;
use App\Enums\MessageStatus;
use App\Models\MessageLog;
use App\Scopes\ChurchScope;

/**
 * Eloquent implementation of the message log writes.
 */
class EloquentMessageLogRepository implements MessageLogRepositoryInterface
{
    /**
     * Writes one line to the message log.
     */
    public function create(array $attributes): MessageLog
    {
        return MessageLog::query()->create($attributes);
    }

    /**
     * The message log row with this id, if there is one.
     */
    public function find(string $id): ?MessageLog
    {
        return MessageLog::query()->withoutGlobalScope(ChurchScope::class)->find($id);
    }

    /**
     * Records that the provider accepted the message.
     */
    public function markSent(MessageLog $log, SmsReceipt $receipt): MessageLog
    {
        $log->update(['status' => MessageStatus::Sent, 'provider_reference' => $receipt->reference, 'provider_response' => $receipt->response, 'sent_at' => now()]);

        return $log;
    }

    /**
     * Records that the message failed for good.
     */
    public function markFailed(MessageLog $log): MessageLog
    {
        $log->update(['status' => MessageStatus::Failed]);

        return $log;
    }

    /**
     * Removes the phone number and text from every logged message to this person, keeping the line itself.
     */
    public function anonymiseRecipient(string $recipientId): void
    {
        MessageLog::query()->where('recipient_id', $recipientId)->update(['recipient_phone' => null, 'recipient_email' => null, 'content' => config('privacy.erased_marker')]);
    }
}
