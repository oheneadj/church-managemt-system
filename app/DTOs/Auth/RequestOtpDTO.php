<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use App\Http\Requests\Auth\RequestOtpRequest;
use App\Models\Church;
use App\ValueObjects\PhoneNumber;

/**
 * A request to be sent a sign-in code: the church the request came to, and the phone number.
 */
final readonly class RequestOtpDTO
{
    public function __construct(public Church $church, public PhoneNumber $phone) {}

    public static function fromRequest(RequestOtpRequest $request): self
    {
        return new self(app('current.church'), $request->phone());
    }
}
