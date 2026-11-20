<?php

declare(strict_types=1);

namespace App\Http\Requests\Church;

use App\Enums\MomoNetwork;
use App\Enums\PayoutMethod;
use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\Models\User;
use App\ValueObjects\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates where the church's digital gifts are to be paid out (docs/08, Payout Account). A mobile money wallet needs its
 * network and a Ghana number; a bank account needs the bank's code and an account number.
 */
class SetPayoutAccountRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may change their church's payout account (the church admin).
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $church = $user instanceof User ? $user->church : null;

        return $church !== null && $this->allowed('update', $church);
    }

    /**
     * Turns a local wallet number such as 0244123456 into international form before it is checked.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('method') === PayoutMethod::Momo->value && is_string($this->input('account_number')) && $this->input('account_number') !== '') {
            $this->merge(['account_number' => PhoneNumber::tryFrom($this->input('account_number'))->e164 ?? $this->input('account_number')]);
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
            'method' => ['required', Rule::enum(PayoutMethod::class)],
            'network' => ['required_if:method,momo', 'prohibited_unless:method,momo', Rule::enum(MomoNetwork::class)],
            'bank_code' => ['required_if:method,bank', 'prohibited_unless:method,bank', 'string', 'regex:/^[A-Za-z0-9]{2,20}$/'],
            'account_number' => [
                'required',
                'string',
                Rule::when($this->input('method') === PayoutMethod::Momo->value, ['regex:'.PhoneNumber::E164_PATTERN], ['regex:/^[0-9]{6,20}$/']),
            ],
        ];
    }
}
