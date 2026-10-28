<?php

declare(strict_types=1);

namespace App\DTOs\Onboarding;

use App\Http\Requests\Onboarding\RequestChurchOtpRequest;
use App\ValueObjects\PhoneNumber;

/**
 * A request to be texted a sign-up code: just the phone number of the person starting a church.
 */
final readonly class RequestChurchOtpDTO
{
    /**
     * Holds the phone.
     */
    public function __construct(public PhoneNumber $phone) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(RequestChurchOtpRequest $request): self
    {
        return new self($request->phone());
    }
}
