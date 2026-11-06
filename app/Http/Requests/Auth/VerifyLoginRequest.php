<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates step two of staff sign-in: the email and the code that was emailed.
 */
class VerifyLoginRequest extends FormRequest
{
    /**
     * Open to everyone: sign-in is public, and its limits protect it.
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
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'code' => ['required', 'string', 'digits:'.config('otp.length')],
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
}
