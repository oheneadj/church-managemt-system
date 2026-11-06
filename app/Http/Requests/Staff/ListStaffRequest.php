<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the staff list's page size.
 */
class ListStaffRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may view any (staff).
     */
    public function authorize(): bool
    {
        return $this->allowed('viewAny', User::class);
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.config('limits.per_page_max')],
        ];
    }
}
