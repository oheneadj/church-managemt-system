<?php

declare(strict_types=1);

namespace App\DTOs\Attendance;

use App\Http\Requests\Attendance\QrCheckInRequest;
use App\Models\Church;
use App\ValueObjects\PhoneNumber;

/**
 * A member confirms their check-in with the scanned code, their phone number and the texted code.
 */
final readonly class QrCheckInDTO
{
    /**
     * Holds the church, the scanned code, the phone and the texted code.
     */
    public function __construct(public Church $church, public string $token, public PhoneNumber $phone, public string $otp) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(QrCheckInRequest $request): self
    {
        return new self(app('current.church'), (string) $request->validated('token'), $request->phone(), (string) $request->validated('otp'));
    }
}
