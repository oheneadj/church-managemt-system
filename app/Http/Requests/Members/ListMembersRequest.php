<?php

declare(strict_types=1);

namespace App\Http\Requests\Members;

use App\Enums\EducationalLevel;
use App\Enums\Gender;
use App\Enums\MemberStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the directory filters, sort and page size (docs/08, List Members).
 */
class ListMembersRequest extends FormRequest
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
            'status' => ['sometimes', Rule::enum(MemberStatus::class)],
            'gender' => ['sometimes', Rule::enum(Gender::class)],
            'educational_level' => ['sometimes', Rule::enum(EducationalLevel::class)],
            'department_id' => ['sometimes', 'ulid'],
            'family_id' => ['sometimes', 'ulid'],
            'search' => ['sometimes', 'string', 'max:100'],
            'sort' => ['sometimes', Rule::in(['last_name', 'first_name', 'created_at', 'membership_date'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
