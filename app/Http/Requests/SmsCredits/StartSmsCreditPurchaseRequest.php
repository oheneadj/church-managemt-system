<?php

declare(strict_types=1);

namespace App\Http\Requests\SmsCredits;

use App\Enums\MomoNetwork;
use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\Models\SmsCreditPurchase;
use App\ValueObjects\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the start of a purchase of SMS credits: a package that is on sale, the MoMo number to send the payment prompt to
 * (written in international form, as every phone in the app is) and its network.
 */
class StartSmsCreditPurchaseRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may buy credits for their church.
     */
    public function authorize(): bool
    {
        return $this->allowed('create', SmsCreditPurchase::class);
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
        return [
            /** @var string */
            'sms_package_id' => ['required', 'ulid', Rule::exists('sms_packages', 'id')->where('is_active', true)],
            'phone' => ['required', 'string', 'regex:'.PhoneNumber::E164_PATTERN],
            'network' => ['required', Rule::enum(MomoNetwork::class)],
        ];
    }

    /**
     * Plain sentences for the rules a person is most likely to hit.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['sms_package_id.exists' => 'That package is not on sale.'];
    }
}
