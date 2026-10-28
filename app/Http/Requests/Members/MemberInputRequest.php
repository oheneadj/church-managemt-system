<?php

declare(strict_types=1);

namespace App\Http\Requests\Members;

use App\Enums\EducationalLevel;
use App\Enums\FamilyRole;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\MembershipType;
use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\Models\Member;
use App\ValueObjects\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The member fields shared by create and update. Create requires the basics; update accepts any subset.
 * The member number and the status are never accepted here: the first never changes, the second has its
 * own endpoint so every change carries a reason.
 */
abstract class MemberInputRequest extends FormRequest
{
    use DelegatesToPolicy;

    /** The member being updated, or null on create. */
    abstract protected function subject(): ?Member;

    /**
     * Normalises phone numbers to international form before they are validated.
     */
    protected function prepareForValidation(): void
    {
        foreach (['phone', 'phone_secondary'] as $field) {
            if (is_string($this->input($field)) && $this->input($field) !== '') {
                $this->merge([$field => PhoneNumber::tryFrom($this->input($field))->e164 ?? $this->input($field)]);
            }
        }
    }

    /**
     * The member field rules shared by create and update. Create requires the basics; update accepts any subset.
     *
     * @return array<string, list<mixed>>
     */
    protected function memberRules(): array
    {
        $partial = $this->subject() !== null;
        $required = $partial ? ['sometimes', 'required'] : ['required'];
        $churchId = app('current.church')->getKey();

        return [
            'first_name' => [...$required, 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => [...$required, 'string', 'max:100'],
            'preferred_name' => ['nullable', 'string', 'max:100'],
            'gender' => [...$required, Rule::enum(Gender::class)],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'phone' => [
                ...$required, 'string', 'regex:'.PhoneNumber::E164_PATTERN,
                // Unique among this church's members who are not deleted (docs/03, data integrity rule 1).
                Rule::unique('members', 'phone')->where('church_id', $churchId)->withoutTrashed()->ignore($this->subject()?->getKey()),
            ],
            'phone_secondary' => ['nullable', 'string', 'regex:'.PhoneNumber::E164_PATTERN],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'nearest_landmark' => ['nullable', 'string', 'max:255'],
            'digital_address' => ['nullable', 'string', 'max:20'],
            'national_id_type' => ['nullable', 'string', 'max:50'],
            'national_id_number' => ['nullable', 'string', 'max:50'],
            'marital_status' => ['nullable', Rule::enum(MaritalStatus::class)],
            'occupation' => ['nullable', 'string', 'max:255'],
            'tribe' => ['nullable', 'string', 'max:100'],
            'language_preference' => ['nullable', 'string', 'max:50'],
            'educational_level' => ['nullable', Rule::enum(EducationalLevel::class)],
            'membership_type' => ['sometimes', Rule::enum(MembershipType::class)],
            'membership_date' => ['nullable', 'date_format:Y-m-d'],
            /** @var string|null */
            'family_id' => ['nullable', 'ulid', Rule::exists('families', 'id')->where('church_id', $churchId)->whereNull('deleted_at')],
            'family_role' => ['nullable', Rule::enum(FamilyRole::class), 'required_with:family_id'],
            /** @var string|null */
            'assigned_pastor_id' => ['nullable', 'ulid', Rule::exists('users', 'id')->where('church_id', $churchId)->whereNull('deleted_at')],
            'whatsapp_opted_in' => ['sometimes', 'boolean'],
            'sms_opted_in' => ['sometimes', 'boolean'],
            'department_ids' => ['sometimes', 'array'],
            /** @var string */
            'department_ids.*' => ['ulid', 'distinct', Rule::exists('departments', 'id')->where('church_id', $churchId)->whereNull('deleted_at')],
        ];
    }
}
