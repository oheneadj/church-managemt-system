<?php

declare(strict_types=1);

namespace App\Http\Requests\Members;

use App\Enums\MemberStatus;
use App\Models\Member;
use Illuminate\Validation\Rule;

/**
 * Validates a new member. The route's policy check has already run, so authorize() lets it through.
 */
class StoreMemberRequest extends MemberInputRequest
{
    /**
     * Asks the policy whether the caller may create (member).
     */
    public function authorize(): bool
    {
        return $this->allowed('create', Member::class);
    }

    /**
     * The member being changed, or null when a member is being added.
     */
    protected function subject(): ?Member
    {
        return null;
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->memberRules(),
            // A new person is either a prospect or already received; the other statuses are for later.
            'status' => ['sometimes', Rule::in([MemberStatus::Prospect->value, MemberStatus::Active->value])],
        ];
    }
}
