<?php

declare(strict_types=1);

namespace App\DTOs\Attendance;

use App\Http\Requests\Attendance\RequestQrCheckinOtpRequest;
use App\Models\Church;
use App\ValueObjects\PhoneNumber;

/**
 * A member at the venue asks for a check-in code to be texted to their phone.
 */
final readonly class RequestQrCheckinOtpDTO
{
    /**
     * Holds the church, the scanned code and the phone number.
     */
    public function __construct(public Church $church, public string $token, public PhoneNumber $phone) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(RequestQrCheckinOtpRequest $request): self
    {
        return new self(app('current.church'), (string) $request->validated('token'), $request->phone());
    }
}
