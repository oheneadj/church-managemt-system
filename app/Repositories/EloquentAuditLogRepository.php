<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\AuditLog;

/**
 * Eloquent implementation of the audit log writes.
 */
class EloquentAuditLogRepository implements AuditLogRepositoryInterface
{
    /**
     * Adds one line to the history. Lines are never changed or removed.
     */
    public function append(array $attributes): AuditLog
    {
        return AuditLog::query()->create($attributes);
    }
}
