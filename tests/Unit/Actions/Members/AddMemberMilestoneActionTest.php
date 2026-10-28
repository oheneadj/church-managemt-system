<?php

declare(strict_types=1);

use App\Actions\Members\AddMemberMilestoneAction;
use App\DTOs\Members\AddMemberMilestoneDTO;
use App\Enums\MilestoneType;
use App\Models\MemberMilestone;
use App\Repositories\MemberMilestoneRepositoryInterface;

it('records the milestone on the member with the staff member who entered it', function (): void {
    $milestone = MemberMilestone::factory()->make(['church_id' => '01churchunit', 'member_id' => '01memberunit', 'created_by' => '01userunit']);
    $milestones = Mockery::mock(MemberMilestoneRepositoryInterface::class);
    $milestones->shouldReceive('create')->once()->with([
        'church_id' => '01churchunit',
        'member_id' => '01memberunit',
        'type' => MilestoneType::Baptism,
        'occurred_at' => '2026-10-19',
        'officiant' => 'Rev. Mensah',
        'cohort' => null,
        'notes' => null,
        'created_by' => '01userunit',
    ])->andReturn($milestone);

    $dto = new AddMemberMilestoneDTO(unitUser(), unitMember(), MilestoneType::Baptism, '2026-10-19', 'Rev. Mensah');

    expect((new AddMemberMilestoneAction($milestones))->execute($dto))->toBe($milestone);
});
