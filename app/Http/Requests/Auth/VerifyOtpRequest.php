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
    /**
     * Open to everyone: sign-in is public, and its rate limits protect it.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalises phone numbers to international form before they are validated.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => PhoneNumber::tryFrom((string) $this->input('phone'))->e164 ?? $this->input('phone')]);
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:'.PhoneNumber::E164_PATTERN],
            'otp' => ['required', 'string', 'digits:'.config('otp.length')],
        ];
    }

    /**
     * The validated phone number as a PhoneNumber.
     */
    public function phone(): PhoneNumber
    {
        return PhoneNumber::tryFrom((string) $this->validated('phone')) ?? throw new \LogicException('Validated phone must parse.');
    }

    /**
     * The validated sign-in code.
     */
    public function code(): string
    {
        return (string) $this->validated('otp');
    }
}
