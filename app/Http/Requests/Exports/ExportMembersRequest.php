<?php

declare(strict_types=1);

namespace App\Http\Requests\Exports;

use App\Enums\StaffPermission;
use App\Http\Requests\Members\ListMembersRequest;
use Illuminate\Support\Arr;

/**
 * Validates a request for the member list as a CSV. It takes the filters of the member list (`GET /members`) and nothing
 * else: the file is always in name order and holds everyone the filters find, so the sort and the page size do not apply.
 */
class ExportMembersRequest extends ListMembersRequest
{
    /**
     * Asks whether the caller may export the member list (the church admin and, by default, the finance secretary, D-055).
     */
    public function authorize(): bool
    {
        return $this->allowed(StaffPermission::MembersExport->value, []);
    }

    /**
     * The rules of the member list's filters, without the sort and the page size.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return Arr::except(parent::rules(), ['sort', 'direction', 'per_page']);
    }
}
