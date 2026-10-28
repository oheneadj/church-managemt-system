<?php

declare(strict_types=1);

namespace App\DTOs\Contributions;

use App\Http\Requests\Services\ShowOfferingReportRequest;
use App\Models\Service;

/**
 * The service whose offering report is asked for.
 */
final readonly class ShowOfferingReportDTO
{
    /**
     * Holds the service route binding found.
     */
    public function __construct(public Service $service) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(ShowOfferingReportRequest $request): self
    {
        return new self($request->service());
    }
}
