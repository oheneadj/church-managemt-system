<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use App\Enums\StaffRole;
use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\Rules\UniqueStaffEmail;
use App\Rules\UniqueStaffPhone;
use App\ValueObjects\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The staff fields shared by invite and update. The phone is put into international form and the email into lowercase
 * before checking, and both must be unused by other staff of this church.
 */
abstract class StaffInputRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Tidies the phone and email before they are validated.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone'))) {
            $this->merge(['phone' => PhoneNumber::tryFrom($this->input('phone'))->e164 ?? $this->input('phone')]);
        }

        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * The name, phone and role rules shared by invite and update. For an update every field is optional.
     *
     * @return array<string, list<mixed>>
     */
    protected function staffRules(bool $partial, ?string $exceptUserId = null): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            'name' => [...$required, 'string', 'max:255'],
            'phone' => [...$required, 'string', 'regex:'.PhoneNumber::E164_PATTERN, new UniqueStaffPhone($exceptUserId)],
            'role' => [...$required, Rule::enum(StaffRole::class)],
        ];
    }

    /**
     * The email rule, used by invite only: an email is the person's sign-in and is not changed here.
     *
     * @return list<mixed>
     */
    protected function emailRules(): array
    {
        return ['required', 'string', 'email:rfc', 'max:255', new UniqueStaffEmail];
    }
}
