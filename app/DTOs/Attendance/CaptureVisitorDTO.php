<?php

declare(strict_types=1);

namespace App\DTOs\Attendance;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Attendance\CaptureVisitorRequest;
use App\Models\Service;
use App\Models\User;
use App\ValueObjects\PhoneNumber;

/**
 * A first-time (or returning) visitor at a service: their name, and optionally their phone and how they heard of the church.
 */
final readonly class CaptureVisitorDTO
{
    use ResolvesActor;

    /**
     * Holds the actor, the service and the visitor's details.
     */
    public function __construct(
        public User $actor,
        public Service $service,
        public string $name,
        public ?PhoneNumber $phone = null,
        public ?string $howHeard = null,
    ) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(CaptureVisitorRequest $request): self
    {
        $v = $request->validated();

        return new self(self::actorFrom($request), $request->service(), $v['name'], isset($v['phone']) ? PhoneNumber::tryFrom($v['phone']) : null, $v['how_heard'] ?? null);
    }
}
