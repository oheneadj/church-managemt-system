<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Services;

use App\Actions\Contributions\ShowOfferingReportAction;
use App\DTOs\Contributions\ShowOfferingReportDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Services\ShowOfferingReportRequest;
use App\Http\Resources\OfferingReportResource;

/**
 * What one service received, by fund and payment method (docs/08, Sunday Offering Report). A service of another church is not found (404).
 */
class ShowOfferingReportController extends Controller
{
    /**
     * Takes the report action.
     */
    public function __construct(private readonly ShowOfferingReportAction $action) {}

    /**
     * Runs ShowOfferingReportAction for this request and returns the OfferingReportResource.
     */
    public function __invoke(ShowOfferingReportRequest $request): OfferingReportResource
    {
        return new OfferingReportResource($this->action->execute(ShowOfferingReportDTO::fromRequest($request)));
    }
}
