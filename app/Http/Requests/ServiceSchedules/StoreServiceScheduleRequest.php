<?php

declare(strict_types=1);

namespace App\Http\Requests\ServiceSchedules;

use App\Models\ServiceSchedule;

/**
 * Validates a new weekly schedule.
 */
class StoreServiceScheduleRequest extends ServiceScheduleInputRequest
{
    /**
     * Asks the policy whether the caller may create (service schedule).
     */
    public function authorize(): bool
    {
        return $this->allowed('create', ServiceSchedule::class);
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->scheduleRules(partial: false);
    }
}
