<?php

declare(strict_types=1);

namespace App\Actions\Members;

use App\DTOs\Members\UpdateMemberDTO;
use App\Events\MemberUpdated;
use App\Models\Member;
use App\Repositories\MemberRepositoryInterface;
use App\Services\MemberDepartmentService;
use App\Services\MemberFamilyService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Applies only the fields that were sent. The member number and status never change here.
 * It fires MemberUpdated, with the old and new values, only when something actually changed.
 */
final class UpdateMemberAction
{
    public function __construct(
        private readonly MemberRepositoryInterface $members,
        private readonly MemberFamilyService $families,
        private readonly MemberDepartmentService $departments,
    ) {}

    public function execute(UpdateMemberDTO $dto): Member
    {
        return DB::transaction(function () use ($dto): Member {
            $member = $dto->member;
            $attributes = $dto->attributes;

            // Dropping the family also drops the role in it.
            if (array_key_exists('family_id', $attributes) && $attributes['family_id'] === null) {
                $attributes['family_role'] = null;
            }

            $member->fill($attributes);
            $changed = $member->getDirty();
            $old = Arr::only($member->getOriginal(), array_keys($changed));

            $this->families->guard($member);
            $this->members->save($member);
            $this->families->claimHead($member);

            if ($dto->departmentIds !== null) {
                $this->departments->sync($member, $dto->departmentIds);
                $changed['department_ids'] = $dto->departmentIds;
            }

            if ($changed !== []) {
                MemberUpdated::dispatch($member, $dto->actor, $old, $changed);
            }

            return $this->members->loadDetail($member, $dto->actor->can('milestones.view'));
        });
    }
}
