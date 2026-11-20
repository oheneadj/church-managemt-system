<?php

declare(strict_types=1);

namespace App\DTOs\Attendance;

use App\Http\Requests\Attendance\ShowQrServiceRequest;
use App\Models\Church;

/**
 * A request to see which service a scanned code is for.
 */
final readonly class ShowQrServiceDTO
{
    /**
     * Holds the church and the scanned code.
     */
    public function __construct(public Church $church, public string $token) {}

    /**
     * Builds the DTO from the request.
     */
    public static function fromRequest(ShowQrServiceRequest $request): self
    {
        return new self(app('current.church'), $request->token());
    }
}
