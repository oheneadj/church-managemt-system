<?php

declare(strict_types=1);

namespace App\DTOs\Departments;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Departments\RemoveDepartmentMemberRequest;
use App\Models\Department;
use App\Models\Member;
use App\Models\User;

/**
 * Taking a member out of a department.
 */
final readonly class RemoveDepartmentMemberDTO
{
    use ResolvesActor;

    /**
     * Holds the actor, department and member.
     */
    public function __construct(public User $actor, public Department $department, public Member $member) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(RemoveDepartmentMemberRequest $request): self
    {
        return new self(self::actorFrom($request), $request->department(), $request->member());
    }
}
