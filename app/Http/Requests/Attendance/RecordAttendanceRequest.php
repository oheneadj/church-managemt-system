<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use App\Enums\CheckInMethod;
use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates marking a list of members present at a service. The list is capped so one request stays small.
 */
class RecordAttendanceRequest extends FormRequest
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
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'member_ids' => ['required', 'array', 'min:1', 'max:'.config('attendance.bulk_max')],
            /** @var string */
            'member_ids.*' => ['required', 'ulid', 'distinct'],
            // Only a manual check-in comes through here; QR and WhatsApp have their own entrances.
            'method' => ['sometimes', Rule::enum(CheckInMethod::class)->only([CheckInMethod::Manual])],
        ];
    }
}
