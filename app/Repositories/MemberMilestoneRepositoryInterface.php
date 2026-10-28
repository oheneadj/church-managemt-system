<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\MemberMilestone;

/**
 * Every query on member milestones lives behind this contract.
 */
interface MemberMilestoneRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): MemberMilestone;
}
