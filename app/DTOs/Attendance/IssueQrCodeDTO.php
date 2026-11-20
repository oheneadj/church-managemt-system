<?php

declare(strict_types=1);

namespace App\DTOs\Attendance;

use App\Http\Requests\Attendance\IssueQrCodeRequest;
use App\Models\Church;
use App\Models\Service;

/**
 * A request for a fresh QR code for a service.
 */
final readonly class IssueQrCodeDTO
{
    /**
     * Holds the church and the service.
     */
    public function __construct(public Church $church, public Service $service) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(IssueQrCodeRequest $request): self
    {
        return new self(app('current.church'), $request->service());
    }
}
