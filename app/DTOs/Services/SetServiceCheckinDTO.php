<?php

declare(strict_types=1);

namespace App\DTOs\Services;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Services\SetServiceCheckinRequest;
use App\Models\Service;
use App\Models\User;

/**
 * Opening or closing a service for check-in.
 */
final readonly class SetServiceCheckinDTO
{
    use ResolvesActor;

    /**
     * Holds the actor, the service and whether check-in should be open.
     */
    public function __construct(public User $actor, public Service $service, public bool $isOpen) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(SetServiceCheckinRequest $request): self
    {
        return new self(self::actorFrom($request), $request->service(), (bool) $request->validated('is_open'));
    }
}
