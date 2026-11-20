<?php

declare(strict_types=1);

namespace App\Http\Requests\FundTypes;

/**
 * Validates a fund type update. Any subset of fields may be sent. Setting is_active to false switches the fund off.
 */
class UpdateFundTypeRequest extends FundTypeInputRequest
{
    /**
     * Asks the policy whether the caller may change this fund type.
     */
    public function authorize(): bool
    {
        return $this->allowed('update', $this->fundType());
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->fundTypeRules(partial: true);
    }
}
