<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Members\ListMembersDTO;
use App\Models\Member;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

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

    /**
     * Saves the member, new or changed.
     */
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

    /**
     * Finds a member of the current church, or fails with a not-found error.
     */
    public function findOrFail(string $id): Member;

    /**
     * The members of the current church with these ids. An id of another church, or one that does not exist, is not returned.
     *
     * @param  list<string>  $ids
     * @return Collection<int, Member>
     */
    public function findMany(array $ids): Collection;

    /**
     * How many members are active in the current church.
     */
    public function countActive(): int;

    /**
     * Soft-deletes the member. The row stays, so records that point to it keep working.
     */
    public function delete(Member $member): void;

    /**
     * Loads everything held about the member that a data export lists: family, departments, milestones and history.
     */
    public function loadForExport(Member $member): Member;
}
