<?php

declare(strict_types=1);

namespace App\DTOs\ServiceSchedules;

use App\DTOs\Concerns\ResolvesActor;
use App\Enums\ServiceType;
use App\Enums\Weekday;
use App\Http\Requests\ServiceSchedules\StoreServiceScheduleRequest;
use App\Models\User;

/**
 * A new weekly schedule.
 */
final readonly class CreateServiceScheduleDTO
{
    use ResolvesActor;

    /**
     * Holds the actor and the schedule's details.
     */
    public function __construct(
        public User $actor,
        public string $name,
        public ServiceType $type,
        public Weekday $weekday,
        public ?string $startsAt = null,
        public ?string $endsAt = null,
        public bool $isActive = true,
    ) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(StoreServiceScheduleRequest $request): self
    {
        $v = $request->validated();

        return new self(self::actorFrom($request), $v['name'], ServiceType::from($v['type']), Weekday::from($v['weekday']), $v['starts_at'] ?? null, $v['ends_at'] ?? null, (bool) ($v['is_active'] ?? true));
    }
}
