<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\MemberStatus;
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

    /** Statuses that take a member out of active life; moving to one needs members.deactivate (docs/03). */
    private const LEAVING = [
        MemberStatus::Inactive,
        MemberStatus::Transferred,
        MemberStatus::Deceased,
        MemberStatus::Archived,
    ];

    /**
     * Whether the user may list members.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('members.view');
    }

    /**
     * Whether the user may open this member.
     */
    public function view(User $user, Member $member): bool
    {
        return $user->church_id === $member->church_id
            && $user->hasPermissionTo('members.view')
            && $this->members->isVisibleTo($user, $member);
    }

    /**
     * Whether the user may add a member.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('members.edit');
    }

    /** A deceased member's record is read-only (docs/03, Stage 4). */
    public function update(User $user, Member $member): bool
    {
        return $user->church_id === $member->church_id
            && $user->hasPermissionTo('members.edit')
            && $member->status !== MemberStatus::Deceased
            && $this->view($user, $member);
    }

    /**
     * Whether the user may move this member to the given status.
     */
    public function changeStatus(User $user, Member $member, MemberStatus $target): bool
    {
        if ($user->church_id !== $member->church_id || ! $user->hasPermissionTo('members.edit') || ! $this->view($user, $member)) {
            return false;
        }

        return in_array($target, self::LEAVING, true) ? $user->hasPermissionTo('members.deactivate') : true;
    }
}
