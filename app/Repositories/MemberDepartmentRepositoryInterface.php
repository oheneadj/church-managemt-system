<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\MemberDepartment;
use Illuminate\Support\Collection;

/**
 * Every query on the member-to-department links lives behind this contract. Links are soft deleted.
 */
interface MemberDepartmentRepositoryInterface
{
    /**
     * Every active department link of a member.
     *
     * @return Collection<int, MemberDepartment>
     */
    public function forMember(string $memberId): Collection;

    /**
     * The active link between a department and a member, if there is one.
     */
    public function find(string $departmentId, string $memberId): ?MemberDepartment;

    /**
     * Links a member to a department.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): MemberDepartment;

    /**
     * Changes the member's role in the department.
     */
    public function changeRole(MemberDepartment $link, ?string $role): MemberDepartment;

    /**
     * Soft deletes the link, so the history is kept.
     */
    public function remove(MemberDepartment $link): void;
}
