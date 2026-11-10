<?php

declare(strict_types=1);

namespace App\Http\Requests\Contributions;

use App\Enums\MemberStatus;
use App\Enums\MomoNetwork;
use App\Enums\PaymentMethod;
use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\Models\Contribution;
use App\Rules\ValidPledgeForGift;
use App\ValueObjects\Money;
use App\ValueObjects\PhoneNumber;
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

    /**
     * Asks the policy whether the caller may record gifts.
     */
    public function authorize(): bool
    {
        return $this->allowed('create', Contribution::class);
    }

    /**
     * Turns a local number such as 0244123456 into international form before it is checked.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone')) && $this->input('phone') !== '') {
            $this->merge(['phone' => PhoneNumber::tryFrom($this->input('phone'))->e164 ?? $this->input('phone')]);
        }
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
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:'.config('giving.min_digital_gift'), 'max:'.config('giving.max_digital_gift')],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)->only([PaymentMethod::Momo])],
            'phone' => ['required', 'string', 'regex:'.PhoneNumber::E164_PATTERN],
            'network' => ['required', Rule::enum(MomoNetwork::class)],
        ];
    }

    /**
     * Plain sentences for the rules a person is most likely to hit, with the limits read from the configuration.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $min = Money::ghs((string) config('giving.min_digital_gift'))->formatted();
        $max = Money::ghs((string) config('giving.max_digital_gift'))->formatted();

        return [
            'amount.min' => "The smallest mobile money gift is {$min}.",
            'amount.max' => "The largest mobile money gift is {$max}.",
            'amount.decimal' => 'Enter the amount in cedis and pesewas, for example 50.00.',
            'payment_method.enum' => 'Only mobile money can be taken for now. Record cash gifts as cash.',
            'phone.regex' => 'Enter the mobile money number of the person giving, for example 0244123456.',
            'network.enum' => 'Choose MTN, Telecel or AirtelTigo.',
        ];
    }
}
