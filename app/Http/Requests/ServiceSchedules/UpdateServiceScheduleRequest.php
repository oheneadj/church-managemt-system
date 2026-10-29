<?php

declare(strict_types=1);

namespace App\Http\Requests\ServiceSchedules;

/**
 * Validates a schedule update. Any subset of the fields may be sent. Turning a schedule off (is_active false) stops new
 * services; the ones already made stay.
 */
class UpdateServiceScheduleRequest extends ServiceScheduleInputRequest
{
    /**
     * Asks the policy whether the caller may change this schedule.
     */
    public function authorize(): bool
    {
        return $this->allowed('update', $this->schedule());
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->scheduleRules(partial: true);
    }
}
