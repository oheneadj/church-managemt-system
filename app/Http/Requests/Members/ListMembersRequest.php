<?php

declare(strict_types=1);

namespace App\Http\Requests\Members;

use App\Enums\EducationalLevel;
use App\Enums\Gender;
use App\Enums\MemberStatus;
use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the directory filters, sort and page size (docs/08, List Members).
 */
class ListMembersRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may view any (member).
     */
    public function authorize(): bool
    {
        return $this->allowed('viewAny', Member::class);
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(MemberStatus::class)],
            'gender' => ['sometimes', Rule::enum(Gender::class)],
            'educational_level' => ['sometimes', Rule::enum(EducationalLevel::class)],
            /** @var string */
            'department_id' => ['sometimes', 'ulid'],
            /** @var string */
            'family_id' => ['sometimes', 'ulid'],
            'search' => ['sometimes', 'string', 'max:100'],
            /** @var 'last_name'|'first_name'|'created_at'|'membership_date' */
            'sort' => ['sometimes', Rule::in(['last_name', 'first_name', 'created_at', 'membership_date'])],
            /** @var 'asc'|'desc' */
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.config('limits.per_page_max')],
        ];
    }
}
