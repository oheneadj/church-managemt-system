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
    public function __construct(public User $viewer, public int $perPage, public ?string $search = null) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(ListFamiliesRequest $request): self
    {
        $v = $request->validated();

        return new self(self::actorFrom($request), (int) ($v['per_page'] ?? config('limits.per_page_default')), $v['search'] ?? null);
    }
}
