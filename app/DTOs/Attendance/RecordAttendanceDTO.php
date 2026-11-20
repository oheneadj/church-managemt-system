<?php

declare(strict_types=1);

namespace App\DTOs\Attendance;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Attendance\RecordAttendanceRequest;
use App\Models\Service;
use App\Models\User;

/**
 * Marking a list of members present at a service, by hand.
 */
final readonly class RecordAttendanceDTO
{
    use ResolvesActor;

    /**
     * Holds the actor, the service and the member ids.
     *
     * @param  list<string>  $memberIds
     */
    public function __construct(public User $actor, public Service $service, public array $memberIds) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(RecordAttendanceRequest $request): self
    {
        return new self(self::actorFrom($request), $request->service(), array_values(array_unique($request->validated('member_ids'))));
    }
}
