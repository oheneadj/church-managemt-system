<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use App\Enums\ReportGrouping;
use App\Enums\StaffPermission;
use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates how far back the attendance trend looks and how it is grouped.
 */
class AttendanceTrendRequest extends FormRequest
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
            'weeks' => ['sometimes', 'integer', 'min:1', 'max:'.config('reports.attendance_weeks_max')],
            'group_by' => ['sometimes', Rule::enum(ReportGrouping::class)],
        ];
    }
}
