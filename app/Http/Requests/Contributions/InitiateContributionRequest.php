<?php

declare(strict_types=1);

namespace App\Http\Requests\Contributions;

use App\Enums\MemberStatus;
use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\Http\Requests\Concerns\ValidatesDigitalGift;
use App\Models\Contribution;
use App\Rules\ValidPledgeForGift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a mobile money gift that starts a payment (docs/08, Initiate MoMo / Card Payment). Everything it points at must
 * belong to this church. The gift keeps to the digital limits (D-018); the limits are checked on the gift, not on the total with
 * the processing fee. Cards are not taken yet.
 */
class InitiateContributionRequest extends FormRequest
{
    use DelegatesToPolicy;
    use ValidatesDigitalGift;

    /**
     * Asks the policy whether the caller may record gifts.
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
            /** @var string|null */
            'pledge_id' => ['nullable', 'ulid', new ValidPledgeForGift],
            'fund_type_id' => ['required', 'ulid', Rule::exists('fund_types', 'id')->where('church_id', $churchId)->where('is_active', true)->whereNull('deleted_at')],
            ...$this->digitalGiftRules(),
        ];
    }

    /**
     * Plain sentences for the rules a person is most likely to hit.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->digitalGiftMessages();
    }
}
