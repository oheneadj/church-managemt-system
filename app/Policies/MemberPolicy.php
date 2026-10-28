<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\MemberStatus;
use App\Enums\StaffPermission;
use App\Models\Member;
use App\Models\User;
use App\Repositories\MemberRepositoryInterface;

/**
 * Who may do what to a member. Every method checks the church as well as the permission, even though the
 * global scope already hides other churches' rows (docs/10, Security).
 */
class MemberPolicy
{
    /**
     * Takes the member repository.
     */
    public function __construct(private readonly MemberRepositoryInterface $members) {}

    /**
     * Whether the user may list members.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(StaffPermission::MembersView);
    }

    /**
     * Whether the user may open this member.
     */
    public function view(User $user, Member $member): bool
    {
        return $user->church_id === $member->church_id
            && $user->hasPermissionTo(StaffPermission::MembersView)
            && $this->members->isVisibleTo($user, $member);
    }

    /**
     * Whether the user may add a member.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo(StaffPermission::MembersEdit);
    }

    /** A deceased member's record is read-only (docs/03, Stage 4). */
    public function update(User $user, Member $member): bool
    {
        return $user->church_id === $member->church_id
            && $user->hasPermissionTo(StaffPermission::MembersEdit)
            && $member->status !== MemberStatus::Deceased
            && $this->view($user, $member);
    }

    /**
     * Whether the user may export everything held about this member (a subject access request).
     */
    public function export(User $user, Member $member): bool
    {
        return $user->church_id === $member->church_id
            && $user->hasPermissionTo(StaffPermission::MembersExport)
            && $this->view($user, $member);
    }

    /**
     * Whether the user may erase this member's personal data. Only the church admin carries this permission.
     */
    public function erase(User $user, Member $member): bool
    {
        return $user->church_id === $member->church_id
            && $user->hasPermissionTo(StaffPermission::MembersErase)
            && $this->view($user, $member);
    }

    /**
     * Whether the user may move this member to the given status.
     */
    public function changeStatus(User $user, Member $member, MemberStatus $target): bool
    {
        if ($user->church_id !== $member->church_id || ! $user->hasPermissionTo(StaffPermission::MembersEdit) || ! $this->view($user, $member)) {
            return false;
        }

        return $target->leavesActiveLife() ? $user->hasPermissionTo(StaffPermission::MembersDeactivate) : true;
    }
}
