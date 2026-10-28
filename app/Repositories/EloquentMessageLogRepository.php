<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\MessageLog;

/**
 * Eloquent implementation of the message log writes.
 */
class EloquentMessageLogRepository implements MessageLogRepositoryInterface
{
    public function create(array $attributes): MessageLog
    {
        return MessageLog::query()->create($attributes);
    }
}
