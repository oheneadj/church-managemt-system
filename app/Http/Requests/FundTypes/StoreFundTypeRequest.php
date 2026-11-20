<?php

declare(strict_types=1);

namespace App\Http\Requests\FundTypes;

use App\Models\FundType;

/**
 * Validates a new fund type.
 */
class StoreFundTypeRequest extends FundTypeInputRequest
{
    /**
     * Asks the policy whether the caller may create (fund type).
     */
    public function authorize(): bool
    {
        return $this->allowed('create', FundType::class);
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->fundTypeRules(partial: false);
    }
}
