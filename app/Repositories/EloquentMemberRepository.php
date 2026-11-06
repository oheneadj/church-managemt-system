<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Members\ListMembersDTO;
use App\DTOs\Messages\BroadcastAudience;
use App\Enums\FamilyRole;
use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\User;
use App\Scopes\ChurchScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\LazyCollection;

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
        return $this->directory($filters)->with('departments:id,name')->paginate($filters->perPage);
    }

    /**
     * Every member the same filters find, a few at a time, with their departments and family, for the CSV export. Nothing is
     * held in memory except the pieces being read.
     */
    public function forExport(ListMembersDTO $filters, int $chunkSize): LazyCollection
    {
        return $this->directory($filters)->with(['departments:id,name', 'family:id,name'])->lazy($chunkSize);
    }

    /**
     * How many people a broadcast to this audience reaches, a shared phone number counted once.
     */
    public function countBroadcastRecipients(BroadcastAudience $audience): int
    {
        return $this->broadcastRecipients($audience)->count();
    }

    /**
     * The members a broadcast to this audience is sent to. Members who must not be texted are never in it (SMS turned off, no
     * phone number, deceased). A church cannot have two members with one phone number (the database allows one), so each person
     * is texted once.
     *
     * @return Builder<Member>
     */
    private function broadcastRecipients(BroadcastAudience $audience): Builder
    {
        return Member::query()
            ->where('sms_opted_in', true)
            ->where('phone', '!=', '')
            ->where('status', '!=', MemberStatus::Deceased->value)
            ->where('status', $audience->status->value)
            ->when($audience->departmentId !== null, fn (Builder $q) => $q->whereHas('departments', fn (Builder $d) => $d->where('departments.id', $audience->departmentId)));
    }

    /**
     * The members this viewer may see, narrowed and ordered by the filters. The list and the export share it, so the file
     * holds what the screen showed.
     *
     * @return Builder<Member>
     */
    private function directory(ListMembersDTO $filters): Builder
    {
        return Member::query()
            ->visibleTo($filters->viewer)
            ->when($filters->status !== null, fn (Builder $q) => $q->where('status', $filters->status?->value))
            ->when($filters->status === null && $filters->search === null, fn (Builder $q) => $q->whereIn('status', array_column(MemberStatus::inDefaultDirectory(), 'value')))
            ->when($filters->gender !== null, fn (Builder $q) => $q->where('gender', $filters->gender?->value))
            ->when($filters->educationalLevel !== null, fn (Builder $q) => $q->where('educational_level', $filters->educationalLevel?->value))
            ->when($filters->familyId !== null, fn (Builder $q) => $q->where('family_id', $filters->familyId))
            ->when($filters->departmentId !== null, fn (Builder $q) => $q->whereHas('departments', fn (Builder $d) => $d->where('departments.id', $filters->departmentId)))
            ->when($filters->search !== null, fn (Builder $q) => $q->search((string) $filters->search))
            ->orderBy($filters->sort, $filters->direction === 'desc' ? 'desc' : 'asc')
            ->orderBy('first_name')
            ->orderBy('id');
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

    /**
     * The members of the current church with these ids. An id of another church, or one that does not exist, is not returned.
     *
     * @param  list<string>  $ids
     * @return Collection<int, Member>
     */
    public function findMany(array $ids): Collection
    {
        return Member::query()->whereKey($ids)->get(['id', 'status']);
    }

    /**
     * The member of the current church with this phone number, if there is one.
     */
    public function findByPhone(string $e164): ?Member
    {
        return Member::query()->where('phone', $e164)->first();
    }

    /**
     * Every living member, in any active church, who has this phone number.
     *
     * @return Collection<int, Member>
     */
    public function findLivingByPhoneInAnyChurch(string $e164): Collection
    {
        return Member::query()
            ->withoutGlobalScope(ChurchScope::class)
            ->with('church')
            ->where('phone', $e164)
            ->where('status', '!=', MemberStatus::Deceased)
            ->whereHas('church', fn (Builder $church) => $church->where('is_active', true))
            ->get();
    }

    /**
     * How many members are active in the current church.
     */
    public function countActive(): int
    {
        return Member::query()->where('status', MemberStatus::Active)->count();
    }
}
