<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\ValueObjects\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates asking for a check-in code: the scanned code and the member's phone number.
 */
class RequestQrCheckinOtpRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Open to everyone: the scanned code proves presence, and the rate limits protect the SMS cost.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Turns a phone number typed in local form into international form before it is validated.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => PhoneNumber::tryFrom((string) $this->input('phone'))->e164 ?? $this->input('phone')]);
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'size:'.config('attendance.qr_token_length')],
            'phone' => ['required', 'string', 'regex:'.PhoneNumber::E164_PATTERN],
        ];
    }

    /**
     * The validated phone number as a PhoneNumber.
     */
    public function phone(): PhoneNumber
    {
        return PhoneNumber::tryFrom((string) $this->validated('phone')) ?? throw new \LogicException('Validated phone must parse.');
    }
}
