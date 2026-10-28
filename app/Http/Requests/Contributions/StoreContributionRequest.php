<?php

declare(strict_types=1);

namespace App\Http\Requests\Contributions;

use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\Models\Contribution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a cash gift. Everything it points at must belong to this church; a member who has died cannot be given
 * a new gift, and a fund that was switched off cannot be chosen. Only cash is recorded here: mobile money and cards
 * start a payment instead and are recorded when the provider confirms it.
 */
class StoreContributionRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may create (contribution).
     */
    public function authorize(): bool
    {
        return $this->allowed('create', Contribution::class);
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $churchId = app('current.church')->getKey();

        return [
            'member_id' => [
                'nullable', 'ulid',
                Rule::exists('members', 'id')->where('church_id', $churchId)->whereNot('status', MemberStatus::Deceased->value)->whereNull('deleted_at'),
            ],
            'service_id' => ['nullable', 'ulid', Rule::exists('services', 'id')->where('church_id', $churchId)->whereNull('deleted_at')],
            'fund_type_id' => ['required', 'ulid', Rule::exists('fund_types', 'id')->where('church_id', $churchId)->where('is_active', true)->whereNull('deleted_at')],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:'.config('giving.cash_max')],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)->only([PaymentMethod::Cash])],
            'contributed_at' => ['nullable', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
