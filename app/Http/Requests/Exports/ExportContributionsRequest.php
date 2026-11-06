<?php

declare(strict_types=1);

namespace App\Http\Requests\Exports;

use App\Enums\StaffPermission;
use App\Http\Requests\Contributions\ListContributionsRequest;
use Illuminate\Support\Arr;

/**
 * Validates a request for the giving records as a CSV. It takes the filters of the gift list (`GET /contributions`) except
 * the voided switch and the page size: the file always holds voided gifts too, flagged, so it can be audited.
 */
class ExportContributionsRequest extends ListContributionsRequest
{
    /**
     * Asks whether the caller may export giving (the church admin and the finance secretary, `giving.export`).
     */
    public function authorize(): bool
    {
        return $this->allowed(StaffPermission::GivingExport->value, []);
    }

    /**
     * The rules of the gift list's filters, without the voided switch and the page size.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return Arr::except(parent::rules(), ['is_voided', 'per_page']);
    }
}
