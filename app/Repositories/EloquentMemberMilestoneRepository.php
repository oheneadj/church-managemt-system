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
}
