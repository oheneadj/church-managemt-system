<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\MemberDepartment;
use Illuminate\Support\Collection;

/**
 * Eloquent implementation of the member-to-department link queries.
 */
class EloquentMemberDepartmentRepository implements MemberDepartmentRepositoryInterface
{
    /**
     * Every active department link of a member.
     */
    public function forMember(string $memberId): Collection
    {
        return MemberDepartment::query()->where('member_id', $memberId)->get();
    }

    /**
     * The active link between a department and a member, if there is one.
     */
    public function find(string $departmentId, string $memberId): ?MemberDepartment
    {
        return MemberDepartment::query()->where('department_id', $departmentId)->where('member_id', $memberId)->first();
    }

    /**
     * Links a member to a department.
     */
    public function create(array $attributes): MemberDepartment
    {
        return MemberDepartment::query()->create($attributes);
    }

    /**
     * Changes the member's role in the department.
     */
    public function changeRole(MemberDepartment $link, ?string $role): MemberDepartment
    {
        $link->update(['role' => $role]);

        return $link;
    }

    /**
     * Soft deletes the link, so the history is kept.
     */
    public function remove(MemberDepartment $link): void
    {
        $link->delete();
    }
}
