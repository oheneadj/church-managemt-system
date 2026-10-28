<?php

declare(strict_types=1);

namespace App\Http\Requests\Pledges;

use App\Enums\MemberStatus;
use App\Models\Pledge;
use Illuminate\Validation\Rule;

/**
 * Validates a new pledge. The member and fund must belong to this church; a member who has died cannot make a pledge,
 * a fund that was switched off cannot be chosen, and the target date cannot be in the past.
 */
class StorePledgeRequest extends PledgeInputRequest
{
    /**
     * Asks the policy whether the caller may create (pledge).
     */
    public function authorize(): bool
    {
        return $this->allowed('create', Pledge::class);
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $churchId = app('current.church')->getKey();
        $rules = $this->pledgeRules(partial: false);
        $rules['target_date'][] = 'after_or_equal:today';

        return [
            'member_id' => [
                'required', 'ulid',
                Rule::exists('members', 'id')->where('church_id', $churchId)->whereNot('status', MemberStatus::Deceased->value)->whereNull('deleted_at'),
            ],
            'fund_type_id' => ['required', 'ulid', Rule::exists('fund_types', 'id')->where('church_id', $churchId)->where('is_active', true)->whereNull('deleted_at')],
            ...$rules,
        ];
    }
}
