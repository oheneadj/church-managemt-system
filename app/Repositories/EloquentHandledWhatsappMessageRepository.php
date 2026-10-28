<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\HandledWhatsappMessage;
use Illuminate\Support\Str;

/**
 * Eloquent implementation of the handled-message queries.
 */
class EloquentHandledWhatsappMessageRepository implements HandledWhatsappMessageRepositoryInterface
{
    /**
     * Marks Meta's message as handled. Returns false when it was already marked.
     */
    public function claim(string $messageId): bool
    {
        return HandledWhatsappMessage::query()->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'message_id' => $messageId,
            'created_at' => now(),
        ]) === 1;
    }
}
