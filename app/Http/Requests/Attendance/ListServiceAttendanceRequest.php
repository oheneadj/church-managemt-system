<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the attendance list's page size.
 */
class ListServiceAttendanceRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may open this service.
     */
    public function authorize(): bool
    {
        return $this->allowed('view', $this->service());
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return ['per_page' => ['sometimes', 'integer', 'min:1', 'max:'.config('limits.per_page_max')]];
    }
}
