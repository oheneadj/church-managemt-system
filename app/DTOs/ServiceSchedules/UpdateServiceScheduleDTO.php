<?php

declare(strict_types=1);

namespace App\DTOs\ServiceSchedules;

use App\Http\Requests\ServiceSchedules\UpdateServiceScheduleRequest;
use App\Models\ServiceSchedule;

/**
 * A partial schedule update: only the fields that were sent (name, type, weekday, starts_at, ends_at, is_active).
 */
final readonly class UpdateServiceScheduleDTO
{
    /**
     * Holds the schedule and the attributes to change.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public ServiceSchedule $schedule, public array $attributes) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(UpdateServiceScheduleRequest $request): self
    {
        return new self($request->schedule(), $request->validated());
    }
}
