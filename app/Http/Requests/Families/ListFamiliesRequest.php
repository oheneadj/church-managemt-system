<?php

declare(strict_types=1);

namespace App\Http\Requests\Families;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the family list's search and page size.
 */
class ListFamiliesRequest extends FormRequest
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
            'search' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
