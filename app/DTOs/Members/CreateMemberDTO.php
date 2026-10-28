<?php

declare(strict_types=1);

namespace App\DTOs\Members;

use App\DTOs\Concerns\ResolvesActor;
use App\Enums\EducationalLevel;
use App\Enums\FamilyRole;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\MembershipType;
use App\Enums\MemberStatus;
use App\Http\Requests\Members\StoreMemberRequest;
use App\Models\User;

/**
 * Everything needed to add a member. Defaults match the database defaults for fields the client left out.
 */
final readonly class CreateMemberDTO
{
    use ResolvesActor;

    /**
     * @param  list<string>  $departmentIds
     */
    public function __construct(
        public User $actor,
        public string $firstName,
        public string $lastName,
        public Gender $gender,
        public string $phone,
        public ?string $middleName = null,
        public ?string $preferredName = null,
        public ?string $dateOfBirth = null,
        public ?string $phoneSecondary = null,
        public ?string $email = null,
        public ?string $address = null,
        public ?string $nearestLandmark = null,
        public ?string $digitalAddress = null,
        public ?string $nationalIdType = null,
        public ?string $nationalIdNumber = null,
        public ?MaritalStatus $maritalStatus = null,
        public ?string $occupation = null,
        public ?string $tribe = null,
        public ?string $languagePreference = null,
        public ?EducationalLevel $educationalLevel = null,
        public MembershipType $membershipType = MembershipType::Full,
        public ?string $membershipDate = null,
        public MemberStatus $status = MemberStatus::Active,
        public ?string $familyId = null,
        public ?FamilyRole $familyRole = null,
        public ?string $assignedPastorId = null,
        public bool $whatsappOptedIn = false,
        public bool $smsOptedIn = true,
        public array $departmentIds = [],
    ) {}

    public static function fromRequest(StoreMemberRequest $request): self
    {
        $v = $request->validated();

        return new self(
            actor: self::actorFrom($request),
            firstName: $v['first_name'],
            lastName: $v['last_name'],
            gender: Gender::from($v['gender']),
            phone: $v['phone'],
            middleName: $v['middle_name'] ?? null,
            preferredName: $v['preferred_name'] ?? null,
            dateOfBirth: $v['date_of_birth'] ?? null,
            phoneSecondary: $v['phone_secondary'] ?? null,
            email: $v['email'] ?? null,
            address: $v['address'] ?? null,
            nearestLandmark: $v['nearest_landmark'] ?? null,
            digitalAddress: $v['digital_address'] ?? null,
            nationalIdType: $v['national_id_type'] ?? null,
            nationalIdNumber: $v['national_id_number'] ?? null,
            maritalStatus: isset($v['marital_status']) ? MaritalStatus::from($v['marital_status']) : null,
            occupation: $v['occupation'] ?? null,
            tribe: $v['tribe'] ?? null,
            languagePreference: $v['language_preference'] ?? null,
            educationalLevel: isset($v['educational_level']) ? EducationalLevel::from($v['educational_level']) : null,
            membershipType: isset($v['membership_type']) ? MembershipType::from($v['membership_type']) : MembershipType::Full,
            membershipDate: $v['membership_date'] ?? null,
            status: isset($v['status']) ? MemberStatus::from($v['status']) : MemberStatus::Active,
            familyId: $v['family_id'] ?? null,
            familyRole: isset($v['family_role']) ? FamilyRole::from($v['family_role']) : null,
            assignedPastorId: $v['assigned_pastor_id'] ?? null,
            whatsappOptedIn: (bool) ($v['whatsapp_opted_in'] ?? false),
            smsOptedIn: (bool) ($v['sms_opted_in'] ?? true),
            departmentIds: array_values($v['department_ids'] ?? []),
        );
    }

    /**
     * The member columns this DTO sets. The church, number, creator and status timestamps are added by the Action.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'first_name' => $this->firstName,
            'middle_name' => $this->middleName,
            'last_name' => $this->lastName,
            'preferred_name' => $this->preferredName,
            'gender' => $this->gender,
            'date_of_birth' => $this->dateOfBirth,
            'phone' => $this->phone,
            'phone_secondary' => $this->phoneSecondary,
            'email' => $this->email,
            'address' => $this->address,
            'nearest_landmark' => $this->nearestLandmark,
            'digital_address' => $this->digitalAddress,
            'national_id_type' => $this->nationalIdType,
            'national_id_number' => $this->nationalIdNumber,
            'marital_status' => $this->maritalStatus,
            'occupation' => $this->occupation,
            'tribe' => $this->tribe,
            'language_preference' => $this->languagePreference,
            'educational_level' => $this->educationalLevel,
            'membership_type' => $this->membershipType,
            'membership_date' => $this->membershipDate,
            'status' => $this->status,
            'family_id' => $this->familyId,
            'family_role' => $this->familyRole,
            'assigned_pastor_id' => $this->assignedPastorId,
            'whatsapp_opted_in' => $this->whatsappOptedIn,
            'sms_opted_in' => $this->smsOptedIn,
        ];
    }
}
