<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates step one of staff sign-in: an email and a password. The email is trimmed and lowercased before it is checked,
 * so capitals and stray spaces never matter.
 */
class LoginRequest extends FormRequest
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
     * The rules that check this request's input. The password is not trimmed or limited in content, only in length.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
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
     * The password as typed.
     */
    public function password(): string
    {
        return (string) $this->validated('password');
    }
}
