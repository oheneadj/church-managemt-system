<?php

declare(strict_types=1);

namespace App\Http\Requests\Church;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\ValueObjects\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates a change to the church's own details: its name, denomination, address, city, region, phone and email. Any of them
 * may be sent, but at least one. The name cannot be emptied; the others can be cleared. The phone is put into international
 * form and the email into lowercase. The web address, time zone, member number letters, plan and credits are not the church's
 * to change here, and sending one is refused rather than silently ignored.
 */
class UpdateChurchProfileRequest extends FormRequest
{
    use DelegatesToPolicy;

    /** The details this request may change. */
    public const FIELDS = ['name', 'denomination', 'address', 'city', 'region', 'phone', 'email'];

    /** Details of the church that are not its to change here: refused if sent, so a client never thinks one was saved. */
    private const PROTECTED = ['slug', 'timezone', 'member_number_prefix', 'plan', 'sms_credits'];

    /**
     * Asks the policy whether the caller may change their church's details.
     */
    public function authorize(): bool
    {
        $church = $this->user()?->church;

        return $church !== null && $this->allowed('update', $church);
    }

    /**
     * Tidies the phone and email before they are validated.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone')) && $this->input('phone') !== '') {
            $this->merge(['phone' => PhoneNumber::tryFrom($this->input('phone'))->e164 ?? $this->input('phone')]);
        }

        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'denomination' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'region' => ['sometimes', 'nullable', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:'.PhoneNumber::E164_PATTERN],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
        ];
    }

    /**
     * Asks for at least one detail to change, and refuses the details that are not the church's to change here. They are
     * checked here and not in the rules, so they do not appear as fields of the request in the API description.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->hasAny(self::FIELDS)) {
                $validator->errors()->add('profile', 'Send at least one detail to change.');
            }

            foreach (self::PROTECTED as $field) {
                if ($this->has($field)) {
                    $validator->errors()->add($field, 'This cannot be changed here.');
                }
            }
        }];
    }
}
