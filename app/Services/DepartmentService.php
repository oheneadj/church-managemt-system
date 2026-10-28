<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Department;
use App\Models\Member;
use App\Models\MemberDepartment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Departments (choir, ushers, ...) and who serves in them. Removing a person from a department soft
 * deletes the link, so the history is kept.
 */
class DepartmentService
{
    /**
     * One page of departments. The member count only counts members the viewer is allowed to see.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Department>
     */
    public function paginate(User $viewer, array $filters): LengthAwarePaginator
    {
        return Department::query()
            ->withCount(['members' => function (Builder $members) use ($viewer): void {
                /** @var Builder<Member> $members */
                $members->visibleTo($viewer);
            }])
            ->when(isset($filters['is_active']), fn (Builder $q) => $q->where('is_active', (bool) $filters['is_active']))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 25));
    }

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function create(User $actor, array $data): Department
    {
        return Department::create([...$data, 'church_id' => $actor->church_id])->refresh();
    }

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function update(Department $department, array $data): Department
    {
        $department->update($data);

        return $department;
    }

    /**
     * Adds a member to the department, or changes their role if they already serve there, so a retried
     * request is harmless. The second element says whether a new link was made.
     *
     * @return array{0: MemberDepartment, 1: bool}
     */
    public function addMember(Department $department, string $memberId, ?string $role): array
    {
        $link = $this->findLink($department, $memberId);

        if ($link !== null) {
            $link->update(['role' => $role]);

            return [$link, false];
        }

        try {
            $link = MemberDepartment::create([
                'church_id' => $department->church_id,
                'member_id' => $memberId,
                'department_id' => $department->getKey(),
                'role' => $role,
                'joined_at' => today(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two requests raced; the database kept one link and the other should find it.
            $link = $this->findLink($department, $memberId) ?? throw new \LogicException('Link vanished after a unique violation.');
            $link->update(['role' => $role]);

            return [$link, false];
        }

        return [$link, true];
    }

    /**
     * @throws ModelNotFoundException when the member is not in the department
     */
    public function removeMember(Department $department, string $memberId): void
    {
        $link = $this->findLink($department, $memberId) ?? throw (new ModelNotFoundException)->setModel(MemberDepartment::class);

        $link->delete();
    }

    private function findLink(Department $department, string $memberId): ?MemberDepartment
    {
        return MemberDepartment::query()->where('department_id', $department->getKey())->where('member_id', $memberId)->first();
    }
}
