<?php

declare(strict_types=1);

namespace App\DTOs\Members;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Members\ExportMemberDataRequest;
use App\Models\Member;
use App\Models\User;

/**
 * A request to export everything held about one member.
 */
final readonly class ExportMemberDataDTO
{
    use ResolvesActor;

    /**
     * Holds the actor and the member.
     */
    public function __construct(public User $actor, public Member $member) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(ExportMemberDataRequest $request): self
    {
        return new self(self::actorFrom($request), $request->member());
    }
}
