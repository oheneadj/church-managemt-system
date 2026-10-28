<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\ValueObjects\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a sign-in code submission.
 */
class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => PhoneNumber::tryFrom((string) $this->input('phone'))->e164 ?? $this->input('phone')]);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
            'otp' => ['required', 'string', 'digits:'.config('otp.length')],
        ];
    }

    public function phone(): PhoneNumber
    {
        return PhoneNumber::tryFrom((string) $this->validated('phone')) ?? throw new \LogicException('Validated phone must parse.');
    }

    public function code(): string
    {
        return (string) $this->validated('otp');
    }
}
