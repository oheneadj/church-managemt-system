<?php

declare(strict_types=1);

namespace App\Actions\Members;

use App\DTOs\Members\ChangeMemberStatusDTO;
use App\Events\MemberStatusChanged;
use App\Models\Member;
use App\Repositories\MemberRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Moves a member to another status, keeping the note as the reason in the change log.
 */
final class ChangeMemberStatusAction
{
    /**
     * Takes the member repository.
     */
    public function __construct(private readonly MemberRepositoryInterface $members) {}

    /**
     * Moves a member to another status, keeping the note as the reason in the change log.
     */
    public function execute(ChangeMemberStatusDTO $dto): Member
    {
        return DB::transaction(function () use ($dto): Member {
            $member = $dto->member;
            $previous = $member->status;

            $member->fill([
                'status' => $dto->status,
                'status_changed_at' => now(),
                'status_note' => $dto->note,
            ]);
            $this->members->save($member);

            MemberStatusChanged::dispatch($member, $dto->actor, $previous, $dto->note);

            return $this->members->loadDetail($member, $dto->actor->can('milestones.view'));
        });
    }
}
