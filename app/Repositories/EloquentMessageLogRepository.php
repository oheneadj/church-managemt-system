<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\MessageLog;

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
     * Removes the phone number and text from every logged message to this person, keeping the line itself.
     */
    public function anonymiseRecipient(string $recipientId): void
    {
        MessageLog::query()->where('recipient_id', $recipientId)->update(['recipient_phone' => null, 'recipient_email' => null, 'content' => config('privacy.erased_marker')]);
    }
}
