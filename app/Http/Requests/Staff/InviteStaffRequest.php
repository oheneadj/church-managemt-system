<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use App\Models\User;

/**
 * Validates an invitation: name, email, phone and role, with a new email and phone.
 */
class InviteStaffRequest extends StaffInputRequest
{
    /**
     * Asks the policy whether the caller may create (staff).
     */
    public function authorize(): bool
    {
        return $this->allowed('create', User::class);
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [...$this->staffRules(partial: false), 'email' => $this->emailRules()];
    }
}
