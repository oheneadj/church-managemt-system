<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

/**
 * Validates a change to a staff member. Any of name, phone and role may be sent; the email cannot be changed here.
 */
class UpdateStaffRequest extends StaffInputRequest
{
    /**
     * Asks the policy whether the caller may change this staff member.
     */
    public function authorize(): bool
    {
        return $this->allowed('update', $this->staffMember());
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->staffRules(partial: true, exceptUserId: $this->staffMember()->id);
    }
}
