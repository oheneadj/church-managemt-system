<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use App\Enums\StaffPermission;
use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates how many months the giving trend looks back and the fund to look at.
 */
class GivingTrendRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks whether the caller may see giving summaries (the church admin, the pastor and the finance secretary). The report
     * holds totals only, so the pastor may read it although not the individual gifts (D-046).
     */
    public function authorize(): bool
    {
        return $this->allowed(StaffPermission::GivingSummary->value, []);
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'months' => ['sometimes', 'integer', 'min:1', 'max:'.config('reports.giving_months_max')],
            /** @var string */
            'fund_type_id' => ['sometimes', 'ulid'],
        ];
    }
}
