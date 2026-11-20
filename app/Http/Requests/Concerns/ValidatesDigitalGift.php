<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\MomoNetwork;
use App\Enums\PaymentMethod;
use App\ValueObjects\Money;
use App\ValueObjects\PhoneNumber;
use Illuminate\Validation\Rule;

/**
 * The rules and plain-word messages every mobile money gift shares, whether a staff member starts it or a person gives on the public
 * page: the amount within the digital limits, mobile money only, the phone and its network.
 */
trait ValidatesDigitalGift
{
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
     * The rules for the amount, the way of paying, the phone and the network.
     *
     * @return array<string, list<mixed>>
     */
    protected function digitalGiftRules(): array
    {
        return [
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
    protected function digitalGiftMessages(): array
    {
        $min = Money::ghs((string) config('giving.min_digital_gift'))->formatted();
        $max = Money::ghs((string) config('giving.max_digital_gift'))->formatted();

        return [
            'amount.min' => "The smallest mobile money gift is {$min}. Enter {$min} or more.",
            'amount.max' => "The largest mobile money gift is {$max}. Enter {$max} or less.",
            'amount.numeric' => 'Enter the amount as a number, for example 50.00.',
            'amount.required' => 'Enter the amount to give.',
            'amount.decimal' => 'Enter the amount in cedis and pesewas, for example 50.00.',
            'payment_method.enum' => 'Only mobile money can be taken for now. Record cash gifts as cash.',
            'phone.regex' => 'Enter the mobile money number of the person giving, for example 0244123456.',
            'network.enum' => 'Choose MTN, Telecel or AirtelTigo.',
        ];
    }
}
