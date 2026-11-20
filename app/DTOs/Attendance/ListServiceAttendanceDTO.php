<?php

declare(strict_types=1);

namespace App\DTOs\Attendance;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Attendance\ListServiceAttendanceRequest;
use App\Models\Service;
use App\Models\User;

/**
 * What the attendance list of a service is asked for: the viewer, the service and the page size.
 */
final readonly class ListServiceAttendanceDTO
{
    use ResolvesActor;

    /**
     * Holds the viewer, the service and the page size.
     */
    public function __construct(public User $viewer, public Service $service, public int $perPage) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(ListServiceAttendanceRequest $request): self
    {
        return new self(self::actorFrom($request), $request->service(), (int) ($request->validated('per_page') ?? config('limits.per_page_default')));
    }
}
