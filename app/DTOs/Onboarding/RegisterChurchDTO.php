<?php

declare(strict_types=1);

namespace App\DTOs\Onboarding;

use App\Http\Requests\Onboarding\RegisterChurchRequest;
use App\ValueObjects\PhoneNumber;

/**
 * Everything needed to start a church: the church, its first admin and the code that proves the admin's phone.
 * `slug` is null when the person asked us to choose the web address from the church name.
 */
final readonly class RegisterChurchDTO
{
    /**
     * Holds the sign-up values.
     */
    public function __construct(
        public string $churchName,
        public ?string $slug,
        public ?string $denomination,
        public ?string $city,
        public string $memberNumberPrefix,
        public string $adminName,
        public PhoneNumber $adminPhone,
        public string $otp,
    ) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(RegisterChurchRequest $request): self
    {
        $data = $request->validated();

        return new self(
            churchName: $data['church_name'],
            slug: $data['slug'] ?? null,
            denomination: $data['denomination'] ?? null,
            city: $data['city'] ?? null,
            memberNumberPrefix: $data['member_number_prefix'],
            adminName: $data['admin_name'],
            adminPhone: PhoneNumber::tryFrom($data['admin_phone']) ?? throw new \LogicException('Validated phone must parse.'),
            otp: $data['otp'],
        );
    }
}
