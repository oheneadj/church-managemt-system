<?php

declare(strict_types=1);

namespace App\Http\Requests\FundTypes;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\Models\FundType;
use App\Rules\UniqueFundTypeName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The fund type fields shared by create and update. A name is unique among this church's fund types, whatever its capitals.
 */
abstract class FundTypeInputRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * The fund type field rules shared by create and update.
     *
     * @return array<string, list<mixed>>
     */
    protected function fundTypeRules(bool $partial): array
    {
        $current = $this->route('fund_type');

        return [
            'name' => [
                ...($partial ? ['sometimes', 'required'] : ['required']), 'string', 'max:100',
                new UniqueFundTypeName($current instanceof FundType ? $current->getKey() : null),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:'.config('giving.sort_order_max')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
