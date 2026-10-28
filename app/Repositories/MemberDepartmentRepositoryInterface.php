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
     * @return Collection<int, MemberDepartment>
     */
    public function forMember(string $memberId): Collection;

    public function find(string $departmentId, string $memberId): ?MemberDepartment;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): MemberDepartment;

    public function changeRole(MemberDepartment $link, ?string $role): MemberDepartment;

    public function remove(MemberDepartment $link): void;
}
