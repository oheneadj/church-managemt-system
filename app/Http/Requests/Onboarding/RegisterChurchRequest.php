<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use App\ValueObjects\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the sign-up of a new church: its name, web address, member number prefix, first admin and the
 * code texted to that admin's phone.
 */
class RegisterChurchRequest extends FormRequest
{
    /**
     * Open to everyone: starting a church is public. The texted code proves the phone, and rate limits protect the endpoint.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Tidies the input before it is validated: phone to international form, slug lowercase (an empty one means
     * "choose for me"), prefix in capitals.
     */
    protected function prepareForValidation(): void
    {
        $slug = str($this->input('slug'))->trim()->lower()->toString();

        $this->merge([
            'admin_phone' => PhoneNumber::tryFrom((string) $this->input('admin_phone'))->e164 ?? $this->input('admin_phone'),
            'slug' => $slug === '' ? null : $slug,
            'member_number_prefix' => str($this->input('member_number_prefix'))->trim()->upper()->toString(),
        ]);
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'church_name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string',
                'min:'.config('onboarding.slug_min_length'), 'max:'.config('onboarding.slug_max_length'),
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::notIn(config('onboarding.reserved_slugs')),
            ],
            'denomination' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'member_number_prefix' => [
                'required', 'string', 'alpha:ascii',
                'min:'.config('onboarding.prefix_min_length'), 'max:'.config('onboarding.prefix_max_length'),
            ],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_phone' => ['required', 'string', 'regex:'.PhoneNumber::E164_PATTERN],
            'otp' => ['required', 'string', 'digits:'.config('otp.length')],
        ];
    }

    /**
     * Plain sentences for the rules a visitor is most likely to hit.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Use lowercase letters, numbers and single hyphens only.',
            'slug.not_in' => 'This web address is kept for the platform. Please choose another.',
            'member_number_prefix.alpha' => 'Use letters only, for example GRC.',
        ];
    }
}
