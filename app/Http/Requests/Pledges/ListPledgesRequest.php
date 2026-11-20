<?php

declare(strict_types=1);

namespace App\Http\Requests\Pledges;

use App\Enums\PledgeStatus;
use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\Models\Pledge;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the pledge list's filters and page size.
 */
class ListPledgesRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may view any (pledge).
     */
    public function authorize(): bool
    {
        return $this->allowed('viewAny', Pledge::class);
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            /** @var string */
            'member_id' => ['sometimes', 'ulid'],
            /** @var string */
            'fund_type_id' => ['sometimes', 'ulid'],
            'status' => ['sometimes', Rule::enum(PledgeStatus::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.config('limits.per_page_max')],
        ];
    }
}
