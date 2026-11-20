<?php

declare(strict_types=1);

namespace App\DTOs\Services;

use App\DTOs\Concerns\ResolvesActor;
use App\Enums\ServiceType;
use App\Http\Requests\Services\StoreServiceRequest;
use App\Models\User;

/**
 * A new service. The check-in code is made by the system, not typed.
 */
final readonly class CreateServiceDTO
{
    use ResolvesActor;

    /**
     * Holds the actor and the service's details.
     */
    public function __construct(
        public User $actor,
        public string $name,
        public ServiceType $type,
        public string $serviceDate,
        public ?string $startsAt = null,
        public ?string $endsAt = null,
        public ?string $notes = null,
    ) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(StoreServiceRequest $request): self
    {
        $v = $request->validated();

        return new self(self::actorFrom($request), $v['name'], ServiceType::from($v['type']), $v['service_date'], $v['starts_at'] ?? null, $v['ends_at'] ?? null, $v['notes'] ?? null);
    }
}
