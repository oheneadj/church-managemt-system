<?php

declare(strict_types=1);

namespace App\Http\Requests\Departments;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the department list's filter and page size.
 */
class ListDepartmentsRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may view any (department).
     */
    public function authorize(): bool
    {
        return $this->allowed('viewAny', Department::class);
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
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
