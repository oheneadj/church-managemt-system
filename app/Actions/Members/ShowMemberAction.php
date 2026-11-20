<?php

declare(strict_types=1);

namespace App\Actions\Members;

use App\DTOs\Members\ShowMemberDTO;
use App\Enums\StaffPermission;
use App\Events\MemberViewed;
use App\Models\Member;
use App\Repositories\MemberRepositoryInterface;

/**
 * Loads the full record of one member and fires MemberViewed, so every look at personal data is on record.
 * Milestones are only loaded for roles allowed to see them (docs/03: finance secretaries and department heads may not).
 */
final class ShowMemberAction
{
    /**
     * Takes the member repository.
     */
    public function __construct(private readonly MemberRepositoryInterface $members) {}

    /**
     * Loads the full record of one member and fires MemberViewed, so every look at personal data is on record.
     */
    public function execute(ShowMemberDTO $dto): Member
    {
        MemberViewed::dispatch($dto->member, $dto->viewer);

        return $this->members->loadDetail($dto->member, $dto->viewer->can(StaffPermission::MilestonesView->value));
    }
}
