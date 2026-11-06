<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Reports\MemberStatusChange;
use App\Models\AuditLog;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * The append-only change history. There is deliberately no update or delete, except that the personal values
 * of an erased record are masked (anonymiseSubject).
 */
interface AuditLogRepositoryInterface
{
    /**
     * Adds one line to the history. Lines are never changed or removed.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function append(array $attributes): AuditLog;

    /**
     * The status history of the current church's members from the first moment to the last (both included): each time a member
     * was added with a status or moved to another one, oldest first. Lines whose values were erased are left out.
     *
     * @return list<MemberStatusChange>
     */
    public function memberStatusChangesBetween(CarbonInterface $from, CarbonInterface $to): array;

    /**
     * Replaces the personal values in a record's history with a marker, keeping every line and the names of the fields. This is the one change ever made to history, required by the right to erasure.
     */
    public function anonymiseSubject(Model $subject): void;

    /**
     * Masks the values in every line of the current church's history, keeping the lines and field names. Used when a cancelled church is purged.
     */
    public function anonymiseAll(): void;
}
