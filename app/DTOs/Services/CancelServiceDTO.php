<?php

declare(strict_types=1);

namespace App\DTOs\Services;

use App\Http\Requests\Services\CancelServiceRequest;
use App\Models\Service;

/**
 * The service to cancel.
 */
final readonly class CancelServiceDTO
{
    /**
     * Holds the service route binding found.
     */
    public function __construct(public Service $service) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(CancelServiceRequest $request): self
    {
        return new self($request->service());
    }
}
