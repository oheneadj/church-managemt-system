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
}
