<?php

declare(strict_types=1);

namespace App\Http\Requests\FundTypes;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\Models\FundType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the fund type list's filter and page size.
 */
class ListFundTypesRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may view any (fund type).
     */
    public function authorize(): bool
    {
        return $this->allowed('viewAny', FundType::class);
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
