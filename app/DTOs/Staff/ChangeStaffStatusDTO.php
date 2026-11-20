<?php

declare(strict_types=1);

namespace App\DTOs\Staff;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Staff\ChangeStaffStatusRequest;
use App\Models\User;

/**
 * Switching a staff member on or off.
 */
final readonly class ChangeStaffStatusDTO
{
    use ResolvesActor;

    /**
     * Holds the church admin, the staff member and the state to set.
     */
    public function __construct(public User $actor, public User $staff, public bool $isActive) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(ChangeStaffStatusRequest $request): self
    {
        return new self(self::actorFrom($request), $request->staffMember(), $request->boolean('is_active'));
    }
}
