<?php

declare(strict_types=1);

namespace App\DTOs\Families;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Families\UpdateFamilyRequest;
use App\Models\Family;
use App\Models\User;

/**
 * A partial family update: the name and/or the notes that were sent.
 */
final readonly class UpdateFamilyDTO
{
    use ResolvesActor;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public User $actor, public Family $family, public array $attributes) {}

    public static function fromRequest(UpdateFamilyRequest $request): self
    {
        return new self(self::actorFrom($request), $request->family(), $request->validated());
    }
}
