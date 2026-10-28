<?php

declare(strict_types=1);

namespace App\DTOs\Services;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Services\UpdateServiceRequest;
use App\Models\Service;
use App\Models\User;

/**
 * Changes to a service. Only the fields that were sent are in `attributes`.
 */
final readonly class UpdateServiceDTO
{
    use ResolvesActor;

    /**
     * Holds the actor, the service and the changed fields.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public User $actor, public Service $service, public array $attributes) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(UpdateServiceRequest $request): self
    {
        return new self(self::actorFrom($request), $request->service(), $request->validated());
    }
}
