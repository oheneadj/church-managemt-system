<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use App\Enums\StaffPermission;
use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates how many services in a row count as absent, and the page size.
 */
class ListAbsentMembersRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks whether the caller may view reports (the church admin, the pastor and the finance secretary).
     */
    public function authorize(): bool
    {
        return $this->allowed(StaffPermission::ReportsView->value, []);
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'consecutive' => ['sometimes', 'integer', 'min:'.config('attendance.absence_threshold_min'), 'max:'.config('attendance.absence_threshold_max')],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.config('limits.per_page_max')],
        ];
    }
}
