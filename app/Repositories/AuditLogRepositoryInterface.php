<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\AuditLog;

/**
 * The append-only change history. There is deliberately no update or delete.
 */
interface AuditLogRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function append(array $attributes): AuditLog;
}
