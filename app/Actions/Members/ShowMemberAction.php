<?php

declare(strict_types=1);

namespace App\Actions\Members;

use App\DTOs\Members\ShowMemberDTO;
use App\Models\Member;
use App\Repositories\MemberRepositoryInterface;

/**
 * Loads the full record of one member. Milestones are only loaded for roles allowed to see them
 * (docs/03: finance secretaries and department heads may not).
 */
final class ShowMemberAction
{
    public function __construct(private readonly MemberRepositoryInterface $members) {}

    public function execute(ShowMemberDTO $dto): Member
    {
        return $this->members->loadDetail($dto->member, $dto->viewer->can('milestones.view'));
    }
}
