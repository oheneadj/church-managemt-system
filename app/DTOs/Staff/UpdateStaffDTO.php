<?php

declare(strict_types=1);

namespace App\DTOs\Staff;

use App\DTOs\Concerns\ResolvesActor;
use App\Enums\StaffRole;
use App\Http\Requests\Staff\UpdateStaffRequest;
use App\Models\User;

/**
 * A change to a staff member's name, phone or role. A field that is not sent is left as it is.
 */
final readonly class UpdateStaffDTO
{
    use ResolvesActor;

    /**
     * Holds the church admin, the staff member and what to change.
     *
     * @param  array<string, string>  $attributes  the name and/or phone to set
     */
    public function __construct(
        public User $actor,
        public User $staff,
        public array $attributes,
        public ?StaffRole $role = null,
    ) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(UpdateStaffRequest $request): self
    {
        $v = $request->validated();

        return new self(
            self::actorFrom($request),
            $request->staffMember(),
            array_intersect_key($v, array_flip(['name', 'phone'])),
            isset($v['role']) ? StaffRole::from($v['role']) : null,
        );
    }
}
