<?php

declare(strict_types=1);

namespace App\Http\Requests\Contributions;

use App\Enums\PaymentMethod;
use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\Models\Contribution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the gift list's filters and page size.
 */
class ListContributionsRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may view any (contribution).
     */
    public function authorize(): bool
    {
        return $this->allowed('viewAny', Contribution::class);
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
            'service_id' => ['sometimes', 'ulid'],
            /** @var string */
            'fund_type_id' => ['sometimes', 'ulid'],
            'payment_method' => ['sometimes', Rule::enum(PaymentMethod::class)],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'is_voided' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.config('limits.per_page_max')],
        ];
    }
}
