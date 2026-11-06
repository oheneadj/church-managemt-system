<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use App\Enums\StaffPermission;
use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates how many months the member growth report looks back.
 */
class MemberGrowthRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks whether the caller may view reports (the church admin, the pastor and the finance secretary). The report is counts
     * only, so the finance secretary may read it too (D-055 point 5).
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
            'months' => ['sometimes', 'integer', 'min:1', 'max:'.config('reports.members_months_max')],
        ];
    }
}
