<?php

declare(strict_types=1);

namespace App\DTOs\Members;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Members\ShowMemberRequest;
use App\Models\Member;
use App\Models\User;

/**
 * Which member to show, and to whom.
 */
final readonly class ShowMemberDTO
{
    use ResolvesActor;

    /**
     * Holds the viewer and member.
     */
    public function __construct(public User $viewer, public Member $member) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(ShowMemberRequest $request): self
    {
        return new self(self::actorFrom($request), $request->member());
    }
}
