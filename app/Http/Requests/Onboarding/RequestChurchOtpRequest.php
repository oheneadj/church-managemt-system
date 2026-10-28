<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use App\ValueObjects\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the request to text a sign-up code to the phone of the person starting a church.
 */
class RequestChurchOtpRequest extends FormRequest
{
    /**
     * Open to everyone: starting a church is public, and its rate limits protect the SMS cost.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalises the phone number to international form before it is validated.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['admin_phone' => PhoneNumber::tryFrom((string) $this->input('admin_phone'))->e164 ?? $this->input('admin_phone')]);
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['admin_phone' => ['required', 'string', 'regex:'.PhoneNumber::E164_PATTERN]];
    }

    /**
     * The validated phone number as a PhoneNumber.
     */
    public function phone(): PhoneNumber
    {
        return PhoneNumber::tryFrom((string) $this->validated('admin_phone')) ?? throw new \LogicException('Validated phone must parse.');
    }
}
