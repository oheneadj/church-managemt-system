<?php

declare(strict_types=1);

namespace App\Http\Requests\Pledges;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The pledge fields shared by create and update: the words, the amount and the date.
 */
abstract class PledgeInputRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * The rules for the fields a pledge can have changed. On create each is required; on update any may be sent.
     *
     * @return array<string, list<mixed>>
     */
    protected function pledgeRules(bool $partial): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            'description' => [...$required, 'string', 'max:255'],
            'amount_pledged' => [...$required, 'numeric', 'decimal:0,2', 'gt:0', 'max:'.config('giving.pledge_max')],
            'target_date' => [...$required, 'date_format:Y-m-d'],
        ];
    }
}
