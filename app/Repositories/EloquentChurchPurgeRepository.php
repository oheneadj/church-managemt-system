<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\AttendanceRecord;
use App\Models\Contribution;
use App\Models\Family;
use App\Models\Member;
use App\Models\MessageLog;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Models\VisitorRecord;
use Closure;

/**
 * Eloquent implementation of the church purge changes. Queries are scoped to the current church by the model's
 * church scope, and deleted rows are included explicitly.
 */
class EloquentChurchPurgeRepository implements ChurchPurgeRepositoryInterface
{
    /** The batch size when walking through every member. */
    private const BATCH = 100;

    /**
     * Calls the callback with every member whose name has not been erased yet, a batch at a time.
     *
     * @param  Closure(Member): void  $callback
     */
    public function eachIdentifiableMember(Closure $callback): void
    {
        Member::query()->withTrashed()
            ->where('first_name', '!=', config('privacy.erased_marker'))
            ->chunkById(self::BATCH, function ($members) use ($callback): void {
                foreach ($members as $member) {
                    $callback($member);
                }
            });
    }

    /**
     * Removes the name, phone and email of every staff user, ends their sessions and soft-deletes them.
     */
    public function anonymiseStaff(): void
    {
        $users = User::query()->withTrashed();
        $ids = (clone $users)->pluck('id');

        PersonalAccessToken::query()->where('tokenable_type', (new User)->getMorphClass())->whereIn('tokenable_id', $ids)->delete();

        $users->update([
            'name' => config('privacy.erased_marker'),
            'phone' => config('privacy.erased_marker'),
            'email' => null,
            'is_active' => false,
            'deleted_at' => now(),
        ]);
    }

    /**
     * Replaces every family's name and removes its notes (a family name is usually a surname).
     */
    public function anonymiseFamilies(): void
    {
        Family::query()->withTrashed()->update(['name' => config('privacy.erased_marker'), 'notes' => null]);
    }

    /**
     * Replaces the name and phone of every visitor record and removes its notes.
     */
    public function anonymiseVisitors(): void
    {
        VisitorRecord::query()->withTrashed()->update([
            'name' => config('privacy.erased_marker'),
            'phone' => config('privacy.erased_marker'),
            'notes' => null,
        ]);
    }

    /**
     * Replaces the visitor names written on attendance records.
     */
    public function anonymiseAttendanceVisitorNames(): void
    {
        AttendanceRecord::query()->whereNotNull('visitor_name')->update(['visitor_name' => config('privacy.erased_marker')]);
    }

    /**
     * Removes the free-text notes of contributions, which can name people. Amounts and dates are kept.
     */
    public function clearContributionNotes(): void
    {
        Contribution::query()->update(['notes' => null]);
        Contribution::query()->whereNotNull('void_reason')->update(['void_reason' => config('privacy.erased_marker')]);
    }

    /**
     * Deletes every logged message of the church.
     */
    public function deleteMessageLogs(): void
    {
        MessageLog::query()->delete();
    }
}
