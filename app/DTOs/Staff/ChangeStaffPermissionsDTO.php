<?php

declare(strict_types=1);

namespace App\DTOs\Staff;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Staff\ChangeStaffPermissionsRequest;
use App\Models\User;

/**
 * Permissions to give a staff member on top of their role, and permissions to take away again.
 */
final readonly class ChangeStaffPermissionsDTO
{
    use ResolvesActor;

    /**
     * Holds the church admin, the staff member and the two lists of permission names.
     *
     * @param  list<string>  $grant
     * @param  list<string>  $revoke
     */
    public function __construct(public User $actor, public User $staff, public array $grant, public array $revoke) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(ChangeStaffPermissionsRequest $request): self
    {
        $v = $request->validated();

        return new self(self::actorFrom($request), $request->staffMember(), array_values($v['grant'] ?? []), array_values($v['revoke'] ?? []));
    }
}
