<?php

declare(strict_types=1);

namespace App\DTOs\Departments;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Departments\UpdateDepartmentRequest;
use App\Models\Department;
use App\Models\User;

/**
 * A partial department update: only the fields that were sent.
 */
final readonly class UpdateDepartmentDTO
{
    use ResolvesActor;

    /**
     * Holds the actor, department and attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public User $actor, public Department $department, public array $attributes) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(UpdateDepartmentRequest $request): self
    {
        return new self(self::actorFrom($request), $request->department(), $request->validated());
    }
}
