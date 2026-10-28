<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\MemberMilestone;

/**
 * Eloquent implementation of the milestone queries.
 */
class EloquentMemberMilestoneRepository implements MemberMilestoneRepositoryInterface
{
    /**
     * Records a milestone.
     */
    public function create(array $attributes): MemberMilestone
    {
        return MemberMilestone::query()->create($attributes);
    }

    /**
     * Removes the free-text notes of a member's milestones, which can name other people.
     */
    public function clearNotesFor(string $memberId): void
    {
        MemberMilestone::query()->where('member_id', $memberId)->update(['notes' => null]);
    }
}
