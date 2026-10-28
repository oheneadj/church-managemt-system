<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Members\ListMembersDTO;
use App\Enums\FamilyRole;
use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent implementation of the member queries.
 */
class EloquentMemberRepository implements MemberRepositoryInterface
{
    /**
     * With no status filter and no search, only prospects and active members are listed; inactive,
     * transferred, deceased and archived members stay out of the default view but can be searched (docs/03).
     */
    public function paginate(ListMembersDTO $filters): LengthAwarePaginator
    {
        return Member::query()
            ->visibleTo($filters->viewer)
            ->with('departments:id,name')
            ->when($filters->status !== null, fn (Builder $q) => $q->where('status', $filters->status?->value))
            ->when($filters->status === null && $filters->search === null, fn (Builder $q) => $q->whereIn('status', array_column(MemberStatus::inDefaultDirectory(), 'value')))
            ->when($filters->gender !== null, fn (Builder $q) => $q->where('gender', $filters->gender?->value))
            ->when($filters->educationalLevel !== null, fn (Builder $q) => $q->where('educational_level', $filters->educationalLevel?->value))
            ->when($filters->familyId !== null, fn (Builder $q) => $q->where('family_id', $filters->familyId))
            ->when($filters->departmentId !== null, fn (Builder $q) => $q->whereHas('departments', fn (Builder $d) => $d->where('departments.id', $filters->departmentId)))
            ->when($filters->search !== null, fn (Builder $q) => $q->search((string) $filters->search))
            ->orderBy($filters->sort, $filters->direction === 'desc' ? 'desc' : 'asc')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate($filters->perPage);
    }

    /**
     * Soft-deletes the member. The row stays, so records that point to it keep working.
     */
    public function delete(Member $member): void
    {
        $member->delete();
    }

    /**
     * Loads everything held about the member that a data export lists: family, departments, milestones and history.
     */
    public function loadForExport(Member $member): Member
    {
        return $member->load(['family', 'departments', 'milestones', 'auditLogs', 'consents']);
    }

    /**
     * Saves the member, new or changed.
     */
    public function save(Member $member): Member
    {
        $member->save();

        return $member;
    }

    /**
     * Whether the member is inside the viewer's part of the directory.
     */
    public function isVisibleTo(User $viewer, Member $member): bool
    {
        return Member::query()->visibleTo($viewer)->whereKey($member->getKey())->exists();
    }

    /**
     * Whether a different member already holds the head role in the family.
     */
    public function familyHasOtherHead(string $familyId, ?string $exceptMemberId): bool
    {
        return Member::query()
            ->where('family_id', $familyId)
            ->where('family_role', FamilyRole::Head->value)
            ->when($exceptMemberId !== null, fn (Builder $q) => $q->whereKeyNot($exceptMemberId))
            ->exists();
    }

    /**
     * Loads the family, departments and pastor, and the milestones when asked.
     */
    public function loadDetail(Member $member, bool $withMilestones): Member
    {
        $member->load(['family', 'departments', 'assignedPastor']);

        if ($withMilestones) {
            $member->load('milestones');
        }

        return $member;
    }

    /**
     * Finds a member of the current church, or fails with a not-found error.
     */
    public function findOrFail(string $id): Member
    {
        return Member::query()->whereKey($id)->firstOrFail();
    }
}
