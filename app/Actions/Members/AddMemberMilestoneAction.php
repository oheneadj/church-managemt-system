<?php

declare(strict_types=1);

namespace App\Actions\Members;

use App\DTOs\Members\AddMemberMilestoneDTO;
use App\Models\MemberMilestone;
use App\Repositories\MemberMilestoneRepositoryInterface;

/**
 * Records a milestone such as a baptism on a member, with the staff member who entered it.
 */
final class AddMemberMilestoneAction
{
    public function __construct(private readonly MemberMilestoneRepositoryInterface $milestones) {}

    public function execute(AddMemberMilestoneDTO $dto): MemberMilestone
    {
        return $this->milestones->create([
            'church_id' => $dto->member->church_id,
            'member_id' => $dto->member->getKey(),
            'type' => $dto->type,
            'occurred_at' => $dto->occurredAt,
            'officiant' => $dto->officiant,
            'cohort' => $dto->cohort,
            'notes' => $dto->notes,
            'created_by' => $dto->actor->getKey(),
        ]);
    }
}
