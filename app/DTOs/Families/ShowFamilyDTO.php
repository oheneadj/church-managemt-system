<?php

declare(strict_types=1);

namespace App\DTOs\Families;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Families\ShowFamilyRequest;
use App\Models\Family;
use App\Models\User;

/**
 * Which family to show, and to whom.
 */
final readonly class ShowFamilyDTO
{
    use ResolvesActor;

    /**
     * Holds the viewer and family.
     */
    public function __construct(public User $viewer, public Family $family) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(ShowFamilyRequest $request): self
    {
        return new self(self::actorFrom($request), $request->family());
    }
}
