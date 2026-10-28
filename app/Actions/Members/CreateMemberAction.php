<?php

declare(strict_types=1);

namespace App\Actions\Members;

use App\DTOs\Members\CreateMemberDTO;
use App\Events\MemberCreated;
use App\Models\Member;
use App\Repositories\MemberRepositoryInterface;
use App\Services\MemberDepartmentService;
use App\Services\MemberFamilyService;
use App\Services\MemberNumberGenerator;
use Illuminate\Support\Facades\DB;

/**
 * Adds a member: numbers them, checks the family rules, sets their departments and fires MemberCreated,
 * all in one transaction so a failure leaves nothing half-saved.
 */
final class CreateMemberAction
{
    public function __construct(
        private readonly MemberRepositoryInterface $members,
        private readonly MemberNumberGenerator $numbers,
        private readonly MemberFamilyService $families,
        private readonly MemberDepartmentService $departments,
    ) {}

    public function execute(CreateMemberDTO $dto): Member
    {
        return DB::transaction(function () use ($dto): Member {
            $member = new Member($dto->attributes());
            $member->church_id = $dto->actor->church_id;
            $member->created_by = $dto->actor->getKey();
            $member->status_changed_at = now();
            $member->member_number = $this->numbers->next($dto->actor->church_id);

            $this->families->guard($member);
            $this->members->save($member);
            $this->families->claimHead($member);
            $this->departments->sync($member, $dto->departmentIds);

            MemberCreated::dispatch($member, $dto->actor);

            return $this->members->loadDetail($member, $dto->actor->can('milestones.view'));
        });
    }
}
