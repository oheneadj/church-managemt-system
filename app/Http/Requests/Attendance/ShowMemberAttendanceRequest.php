<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates how many weeks back a member's attendance report looks.
 */
class ShowMemberAttendanceRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may open this member.
     */
    public function authorize(): bool
    {
        return $this->allowed('view', $this->member());
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return ['weeks' => ['sometimes', 'integer', 'min:1', 'max:'.config('attendance.member_report_max_weeks')]];
    }
}
