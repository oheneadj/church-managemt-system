<?php

declare(strict_types=1);

namespace App\Http\Requests\ServiceSchedules;

use App\Enums\ServiceType;
use App\Enums\Weekday;
use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The schedule fields shared by create and update.
 */
abstract class ServiceScheduleInputRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * The rules for the fields of a schedule. On create the name, type and weekday are required; on update any may be sent.
     *
     * @return array<string, list<mixed>>
     */
    protected function scheduleRules(bool $partial): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            'name' => [...$required, 'string', 'max:255'],
            'type' => [...$required, Rule::enum(ServiceType::class)],
            'weekday' => [...$required, Rule::enum(Weekday::class)],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i', 'after:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
