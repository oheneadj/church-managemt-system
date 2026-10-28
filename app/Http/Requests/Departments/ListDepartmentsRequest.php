<?php

declare(strict_types=1);

namespace App\Http\Requests\Departments;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the department list's filter and page size.
 */
class ListDepartmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
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
