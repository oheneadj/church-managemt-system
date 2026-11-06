<?php

declare(strict_types=1);

namespace App\DTOs\Staff;

use App\DTOs\Concerns\ResolvesActor;
use App\Enums\StaffRole;
use App\Http\Requests\Staff\InviteStaffRequest;
use App\Models\User;

/**
 * A new staff member to invite.
 */
final readonly class InviteStaffDTO
{
    use ResolvesActor;

    /**
     * Holds the church admin and the new staff member's details.
     */
    public function __construct(
        public User $actor,
        public string $name,
        public string $email,
        public string $phone,
        public StaffRole $role,
    ) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(InviteStaffRequest $request): self
    {
        $v = $request->validated();

        return new self(self::actorFrom($request), $v['name'], $v['email'], $v['phone'], StaffRole::from($v['role']));
    }
}
