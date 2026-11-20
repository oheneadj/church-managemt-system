<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\ValueObjects\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a visitor: a name, and optionally a phone number (normalised before it is checked) and how they heard of the church.
 */
class CaptureVisitorRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may record attendance at this service.
     */
    public function authorize(): bool
    {
        return $this->allowed('recordAttendance', $this->service());
    }

    /**
     * Turns a phone number typed in local form into international form before it is validated.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone')) && $this->input('phone') !== '') {
            $this->merge(['phone' => PhoneNumber::tryFrom($this->input('phone'))->e164 ?? $this->input('phone')]);
        }
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:'.PhoneNumber::E164_PATTERN],
            'how_heard' => ['nullable', 'string', 'max:255'],
        ];
    }
}
