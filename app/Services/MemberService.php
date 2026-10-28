<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditEvent;
use App\Enums\FamilyRole;
use App\Enums\MemberStatus;
use App\Models\Family;
use App\Models\Member;
use App\Models\MemberDepartment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The member directory's business rules: listing, adding, editing and changing status (docs/03).
 * Every write runs in one transaction and leaves a line in the change log.
 */
class MemberService
{
    /** Fields whose values are encrypted at rest; the change log records that they changed, never what to. */
    private const SENSITIVE = ['address', 'national_id_number'];

    public function __construct(
        private readonly MemberNumberGenerator $numbers,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * One page of the directory, limited to what the viewer may see.
     * With no status filter and no search, only prospects and active members are listed; inactive,
     * transferred, deceased and archived members stay out of the default view but can be searched (docs/03).
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Member>
     */
    public function paginate(User $viewer, array $filters): LengthAwarePaginator
    {
        $search = Arr::get($filters, 'search');

        return Member::query()
            ->visibleTo($viewer)
            ->with('departments:id,name')
            ->when(isset($filters['status']), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when(! isset($filters['status']) && ! isset($search), fn (Builder $q) => $q->whereIn('status', [MemberStatus::Prospect->value, MemberStatus::Active->value]))
            ->when(isset($filters['gender']), fn (Builder $q) => $q->where('gender', $filters['gender']))
            ->when(isset($filters['educational_level']), fn (Builder $q) => $q->where('educational_level', $filters['educational_level']))
            ->when(isset($filters['family_id']), fn (Builder $q) => $q->where('family_id', $filters['family_id']))
            ->when(isset($filters['department_id']), fn (Builder $q) => $q->whereHas('departments', fn (Builder $d) => $d->where('departments.id', $filters['department_id'])))
            ->when(isset($search), fn (Builder $q) => $q->search((string) $search))
            ->orderBy($filters['sort'] ?? 'last_name', $filters['direction'] ?? 'asc')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 25));
    }

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function create(User $actor, array $data): Member
    {
        return DB::transaction(function () use ($actor, $data): Member {
            $departmentIds = Arr::pull($data, 'department_ids', []);

            $member = new Member($data);
            $member->church_id = $actor->church_id;
            $member->created_by = $actor->getKey();
            $member->status ??= MemberStatus::Active;
            $member->status_changed_at = now();
            $member->member_number = $this->numbers->next($actor->church_id);

            $this->guardFamily($member);
            $member->save();
            $this->claimHeadOfFamily($member);
            $this->syncDepartments($member, $departmentIds);

            $this->audit->record($member, AuditEvent::Created, $actor, new: $this->forAudit($member->getAttributes()));

            return $member->load(['family', 'departments', 'milestones', 'assignedPastor']);
        });
    }

    /**
     * Applies only the fields that were sent. The member number and status never change here.
     *
     * @param  array<string, mixed>  $data  validated input
     */
    public function update(User $actor, Member $member, array $data): Member
    {
        return DB::transaction(function () use ($actor, $member, $data): Member {
            $departmentIds = Arr::pull($data, 'department_ids');

            // Dropping the family also drops the role in it.
            if (array_key_exists('family_id', $data) && $data['family_id'] === null) {
                $data['family_role'] = null;
            }

            $member->fill($data);
            $changed = $member->getDirty();
            $old = Arr::only($member->getOriginal(), array_keys($changed));

            $this->guardFamily($member);
            $member->save();
            $this->claimHeadOfFamily($member);

            if ($departmentIds !== null) {
                $this->syncDepartments($member, $departmentIds);
                $changed['department_ids'] = $departmentIds;
            }

            if ($changed !== []) {
                $this->audit->record($member, AuditEvent::Updated, $actor, $this->forAudit($old), $this->forAudit($changed));
            }

            return $member->load(['family', 'departments', 'milestones', 'assignedPastor']);
        });
    }

    public function changeStatus(User $actor, Member $member, MemberStatus $status, ?string $note): Member
    {
        return DB::transaction(function () use ($actor, $member, $status, $note): Member {
            $previous = $member->status;

            $member->update([
                'status' => $status,
                'status_changed_at' => now(),
                'status_note' => $note,
            ]);

            $this->audit->record(
                $member,
                AuditEvent::StatusChanged,
                $actor,
                ['status' => $previous->value],
                ['status' => $status->value],
                $note,
            );

            return $member->load(['family', 'departments', 'milestones', 'assignedPastor']);
        });
    }

    /**
     * One head of household per family (docs/03). The family row is locked first, so two requests that
     * both try to become head cannot both pass the check.
     */
    private function guardFamily(Member $member): void
    {
        if ($member->family_id === null && $member->family_role !== null) {
            throw ValidationException::withMessages(['family_id' => 'Choose a family before giving a role in it.']);
        }

        if ($member->family_id === null || $member->family_role !== FamilyRole::Head) {
            return;
        }

        Family::query()->whereKey($member->family_id)->lockForUpdate()->firstOrFail();

        $otherHead = Member::query()
            ->where('family_id', $member->family_id)
            ->where('family_role', FamilyRole::Head->value)
            ->when($member->exists, fn (Builder $q) => $q->whereKeyNot($member->getKey()))
            ->exists();

        if ($otherHead) {
            throw ValidationException::withMessages(['family_role' => 'This family already has a head of household.']);
        }
    }

    /** Keeps families.head_member_id in step with the member who holds the head role. */
    private function claimHeadOfFamily(Member $member): void
    {
        if ($member->family_id !== null && $member->family_role === FamilyRole::Head) {
            Family::query()->whereKey($member->family_id)->update(['head_member_id' => $member->getKey()]);
        }
    }

    /**
     * Makes the member's departments exactly this list. Rows that are no longer wanted are soft deleted,
     * never removed (no hard deletes).
     *
     * @param  list<string>  $departmentIds
     */
    private function syncDepartments(Member $member, array $departmentIds): void
    {
        $current = MemberDepartment::query()->where('member_id', $member->getKey())->get();

        $current->reject(fn (MemberDepartment $row) => in_array($row->department_id, $departmentIds, true))
            ->each(fn (MemberDepartment $row) => $row->delete());

        foreach (array_diff($departmentIds, $current->pluck('department_id')->all()) as $departmentId) {
            MemberDepartment::create([
                'church_id' => $member->church_id,
                'member_id' => $member->getKey(),
                'department_id' => $departmentId,
                'joined_at' => today(),
            ]);
        }
    }

    /**
     * Hides the values of encrypted fields before they go into the change log.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function forAudit(array $values): array
    {
        foreach (self::SENSITIVE as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = '[changed]';
            }
        }

        return $values;
    }
}
