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
     * Records a milestone.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): MemberMilestone;

    /**
     * Removes the free-text notes of a member's milestones, which can name other people.
     */
    public function clearNotesFor(string $memberId): void;
}
