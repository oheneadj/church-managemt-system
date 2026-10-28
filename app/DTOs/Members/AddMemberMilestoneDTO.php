<?php

declare(strict_types=1);

namespace App\DTOs\Members;

use App\DTOs\Concerns\ResolvesActor;
use App\Enums\MilestoneType;
use App\Http\Requests\Members\StoreMilestoneRequest;
use App\Models\Member;
use App\Models\User;

/**
 * A milestone such as a baptism, to record on a member.
 */
final readonly class AddMemberMilestoneDTO
{
    use ResolvesActor;

    /**
     * Holds the values this operation needs.
     */
    public function __construct(
        public User $actor,
        public Member $member,
        public MilestoneType $type,
        public string $occurredAt,
        public ?string $officiant = null,
        public ?string $cohort = null,
        public ?string $notes = null,
    ) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(StoreMilestoneRequest $request): self
    {
        $v = $request->validated();

        return new self(
            actor: self::actorFrom($request),
            member: $request->member(),
            type: MilestoneType::from($v['type']),
            occurredAt: $v['occurred_at'],
            officiant: $v['officiant'] ?? null,
            cohort: $v['cohort'] ?? null,
            notes: $v['notes'] ?? null,
        );
    }
}
