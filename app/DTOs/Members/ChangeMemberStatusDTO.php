<?php

declare(strict_types=1);

namespace App\DTOs\Members;

use App\DTOs\Concerns\ResolvesActor;
use App\Enums\MemberStatus;
use App\Http\Requests\Members\UpdateMemberStatusRequest;
use App\Models\Member;
use App\Models\User;

/**
 * Moving a member to another status, with the reason kept in the change log.
 */
final readonly class ChangeMemberStatusDTO
{
    use ResolvesActor;

    public function __construct(
        public User $actor,
        public Member $member,
        public MemberStatus $status,
        public ?string $note = null,
    ) {}

    public static function fromRequest(UpdateMemberStatusRequest $request): self
    {
        return new self(self::actorFrom($request), $request->member(), $request->status(), $request->validated('note'));
    }
}
