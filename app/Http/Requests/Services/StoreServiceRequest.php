<?php

declare(strict_types=1);

namespace App\Http\Requests\Services;

use App\Enums\ServiceType;
use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a new service. The check-in code is never accepted here: the system makes it.
 */
class StoreServiceRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may create (service).
     */
    public function authorize(): bool
    {
        return $this->allowed('create', Service::class);
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ServiceType::class)],
            'service_date' => ['required', 'date_format:Y-m-d'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i', 'after:starts_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
