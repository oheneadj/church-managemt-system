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
    public function forMember(string $memberId): Collection
    {
        return MemberDepartment::query()->where('member_id', $memberId)->get();
    }

    public function find(string $departmentId, string $memberId): ?MemberDepartment
    {
        return MemberDepartment::query()->where('department_id', $departmentId)->where('member_id', $memberId)->first();
    }

    public function create(array $attributes): MemberDepartment
    {
        return MemberDepartment::create($attributes);
    }

    public function changeRole(MemberDepartment $link, ?string $role): MemberDepartment
    {
        $link->update(['role' => $role]);

        return $link;
    }

    public function remove(MemberDepartment $link): void
    {
        $link->delete();
    }
}
