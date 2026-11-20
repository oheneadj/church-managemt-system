<?php

declare(strict_types=1);

namespace App\Http\Requests\Pledges;

use App\Models\Pledge;

/**
 * Validates a pledge update. Any of the description, amount and target date may be sent. The member and fund cannot
 * change, because gifts are linked to them (D-047).
 */
class UpdatePledgeRequest extends PledgeInputRequest
{
    /**
     * Asks the policy whether the caller may change this pledge.
     */
    public function authorize(): bool
    {
        return $this->allowed('update', $this->pledge());
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = $this->pledgeRules(partial: true);

        // A new target date must be today or later; saving the pledge again with its old date (even one that has passed) is fine.
        $pledge = $this->route('pledge');

        if ($pledge instanceof Pledge && $this->has('target_date') && $this->input('target_date') !== $pledge->target_date->toDateString()) {
            $rules['target_date'][] = 'after_or_equal:today';
        }

        return $rules;
    }
}
