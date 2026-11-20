<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Member;
use Closure;

/**
 * The bulk changes a church purge makes (docs/05). Every method works on the church that is bound as the current
 * church, and includes soft-deleted rows, because a deleted record can still hold personal details.
 */
interface ChurchPurgeRepositoryInterface
{
    /**
     * Calls the callback with every member whose name has not been erased yet, a batch at a time.
     *
     * @param  Closure(Member): void  $callback
     */
    public function eachIdentifiableMember(Closure $callback): void;

    /**
     * Removes the name, phone and email of every staff user, ends their sessions and soft-deletes them.
     */
    public function anonymiseStaff(): void;

    /**
     * Replaces every family's name and removes its notes (a family name is usually a surname).
     */
    public function anonymiseFamilies(): void;

    /**
     * Replaces the name and phone of every visitor record and removes its notes.
     */
    public function anonymiseVisitors(): void;

    /**
     * Replaces the visitor names written on attendance records.
     */
    public function anonymiseAttendanceVisitorNames(): void;

    /**
     * Removes the free-text notes of contributions, which can name people. Amounts and dates are kept.
     */
    public function clearContributionNotes(): void;

    /**
     * Deletes every logged message of the church.
     */
    public function deleteMessageLogs(): void;
}
