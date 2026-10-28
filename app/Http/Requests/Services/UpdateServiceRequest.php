<?php

declare(strict_types=1);

namespace App\Http\Requests\Services;

use App\Enums\ServiceType;
use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates changes to a service. Any subset of the fields may be sent. A manual headcount stands in for the
 * digital count when a church counts heads on paper.
 */
class UpdateServiceRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may change this service.
     */
    public function authorize(): bool
    {
        return $this->allowed('update', $this->service());
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::enum(ServiceType::class)],
            'service_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'starts_at' => ['sometimes', 'nullable', 'date_format:H:i'],
            'ends_at' => ['sometimes', 'nullable', 'date_format:H:i', 'after:starts_at'],
            'headcount_override' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
