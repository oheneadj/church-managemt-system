<?php

declare(strict_types=1);

namespace App\Http\Requests\ServiceSchedules;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\Models\ServiceSchedule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the schedule list's filter and page size.
 */
class ListServiceSchedulesRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may view any (service schedule).
     */
    public function authorize(): bool
    {
        return $this->allowed('viewAny', ServiceSchedule::class);
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.config('limits.per_page_max')],
        ];
    }
}
