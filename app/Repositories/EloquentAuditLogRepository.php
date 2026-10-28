<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\AuditLog;

/**
 * Eloquent implementation of the audit log writes.
 */
class EloquentAuditLogRepository implements AuditLogRepositoryInterface
{
    public function append(array $attributes): AuditLog
    {
        return AuditLog::query()->create($attributes);
    }
}
