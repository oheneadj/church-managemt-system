<?php

declare(strict_types=1);

namespace App\DTOs\Members;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Members\EraseMemberRequest;
use App\Models\Member;
use App\Models\User;

/**
 * A request to erase one member's personal data, with the reason kept in the change log.
 */
final readonly class EraseMemberDTO
{
    use ResolvesActor;

    /**
     * Holds the actor, the member and the reason.
     */
    public function __construct(public User $actor, public Member $member, public string $reason) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(EraseMemberRequest $request): self
    {
        return new self(self::actorFrom($request), $request->member(), (string) $request->validated('reason'));
    }
}
