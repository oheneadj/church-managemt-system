<?php

declare(strict_types=1);

namespace App\DTOs\Families;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Families\ListFamiliesRequest;
use App\Models\User;

/**
 * What the family list is asked for: the viewer, an optional name search and the page size.
 */
final readonly class ListFamiliesDTO
{
    use ResolvesActor;

    /**
     * Holds the viewer, search and per page.
     */
    public function __construct(public User $viewer, public ?string $search = null, public int $perPage = 25) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(ListFamiliesRequest $request): self
    {
        $v = $request->validated();

        return new self(self::actorFrom($request), $v['search'] ?? null, (int) ($v['per_page'] ?? 25));
    }
}
