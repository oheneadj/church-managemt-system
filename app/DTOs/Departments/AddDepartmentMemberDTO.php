<?php

declare(strict_types=1);

namespace App\DTOs\Departments;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Departments\AddDepartmentMemberRequest;
use App\Models\Department;
use App\Models\User;

/**
 * Putting a member in a department, or changing their role there.
 */
final readonly class AddDepartmentMemberDTO
{
    use ResolvesActor;

    public function __construct(
        public User $actor,
        public Department $department,
        public string $memberId,
        public ?string $role = null,
    ) {}

    public static function fromRequest(AddDepartmentMemberRequest $request): self
    {
        $v = $request->validated();

        return new self(self::actorFrom($request), $request->department(), $v['member_id'], $v['role'] ?? null);
    }
}
