<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\MemberMilestone;

/**
 * Eloquent implementation of the milestone queries.
 */
class EloquentMemberMilestoneRepository implements MemberMilestoneRepositoryInterface
{
    public function create(array $attributes): MemberMilestone
    {
        return MemberMilestone::create($attributes);
    }
}
