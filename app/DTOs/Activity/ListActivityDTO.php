<?php

declare(strict_types=1);

namespace App\DTOs\Activity;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Activity\ListActivityRequest;
use App\Models\User;

/**
 * What the activity feed is asked for: who is asking, so the feed shows only what they may see, and the page size.
 */
final readonly class ListActivityDTO
{
    use ResolvesActor;

    /**
     * Holds the viewer and the page size.
     */
    public function __construct(public User $viewer, public int $perPage) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(ListActivityRequest $request): self
    {
        return new self(self::actorFrom($request), (int) ($request->validated()['per_page'] ?? config('limits.per_page_default')));
    }
}
