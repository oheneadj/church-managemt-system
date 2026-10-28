<?php

declare(strict_types=1);

namespace App\DTOs\Attendance;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Attendance\ListAbsentMembersRequest;
use App\Models\User;

/**
 * What the absent-members list is asked for: the viewer, how many services in a row, and the page size.
 */
final readonly class ListAbsentMembersDTO
{
    use ResolvesActor;

    /**
     * Holds the viewer, the number of consecutive services and the page size.
     */
    public function __construct(public User $viewer, public int $consecutive, public int $perPage) {}

    /**
     * Builds the DTO from the validated request. Without a number, the church's own setting (or the default) is used.
     */
    public static function fromRequest(ListAbsentMembersRequest $request): self
    {
        $viewer = self::actorFrom($request);

        return new self(
            $viewer,
            (int) ($request->validated('consecutive') ?? app('current.church')->absenceThreshold()),
            (int) ($request->validated('per_page') ?? config('limits.per_page_default')),
        );
    }
}
