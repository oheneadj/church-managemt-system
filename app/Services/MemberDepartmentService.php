<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Member;
use App\Repositories\MemberDepartmentRepositoryInterface;

/**
 * Makes a member's departments match a list, for every Action that sets departments together with a member.
 * Links that are no longer wanted are soft deleted, never removed (no hard deletes).
 */
class MemberDepartmentService
{
    /**
     * Takes the member department repository.
     */
    public function __construct(private readonly MemberDepartmentRepositoryInterface $links) {}

    /**
     * Makes the member's departments match the list, soft deleting links that are no longer wanted.
     *
     * @param  list<string>  $departmentIds
     */
    public function sync(Member $member, array $departmentIds): void
    {
        $current = $this->links->forMember($member->getKey());

        $current->reject(fn ($link) => in_array($link->department_id, $departmentIds, true))
            ->each(fn ($link) => $this->links->remove($link));

        foreach (array_diff($departmentIds, $current->pluck('department_id')->all()) as $departmentId) {
            $this->links->create([
                'church_id' => $member->church_id,
                'member_id' => $member->getKey(),
                'department_id' => $departmentId,
                'joined_at' => today(),
            ]);
        }
    }
}
