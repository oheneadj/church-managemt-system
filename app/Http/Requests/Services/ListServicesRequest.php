<?php

declare(strict_types=1);

namespace App\Http\Requests\Services;

use App\Enums\ServiceType;
use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the service list's filters and page size.
 */
class ListServicesRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may view any (service).
     */
    public function authorize(): bool
    {
        return $this->allowed('viewAny', Service::class);
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'type' => ['sometimes', Rule::enum(ServiceType::class)],
            'is_open' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.config('limits.per_page_max')],
        ];
    }
}
