<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Members\ListMembersDTO;
use App\Models\Member;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Every query on members lives behind this contract, so Actions and Services never touch Eloquent for them.
 */
interface MemberRepositoryInterface
{
    /**
     * One page of the directory, limited to what the viewer may see.
     *
     * @return LengthAwarePaginator<int, Member>
     */
    public function paginate(ListMembersDTO $filters): LengthAwarePaginator;

    public function save(Member $member): Member;

    /**
     * Whether the viewer is allowed to see this member (docs/03 roles table, D-031).
     */
    public function isVisibleTo(User $viewer, Member $member): bool;

    /**
     * Whether another member already holds the head role in this family.
     */
    public function familyHasOtherHead(string $familyId, ?string $exceptMemberId): bool;

    /**
     * Loads what the full member record shows. Milestones are only loaded when asked for.
     */
    public function loadDetail(Member $member, bool $withMilestones): Member;

    public function findOrFail(string $id): Member;
}
