<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Rules\StaffPasswordRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates choosing a new password: the email, the emailed code, and the password with its confirmation. The password is
 * checked here, before the code is looked at, so a refused password never uses the code up.
 */
class ResetPasswordRequest extends FormRequest
{
    /**
     * Open to everyone: it is public, and its limits protect it.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalises the email before it is validated.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'code' => ['required', 'string', 'digits:'.config('otp.length')],
            'password' => ['required', 'string', 'confirmed', new StaffPasswordRule],
        ];
    }

    /**
     * The validated, normalised email.
     */
    public function email(): string
    {
        return (string) $this->validated('email');
    }

    /**
     * The validated code.
     */
    public function code(): string
    {
        return (string) $this->validated('code');
    }

    /**
     * The new password as typed.
     */
    public function password(): string
    {
        return (string) $this->validated('password');
    }
}
