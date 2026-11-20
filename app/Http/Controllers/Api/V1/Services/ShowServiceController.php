<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Services;

use App\Actions\Services\ShowServiceAction;
use App\DTOs\Services\ShowServiceDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Services\ShowServiceRequest;
use App\Http\Resources\ServiceResource;

/**
 * One service with its attendance count (docs/08). A service of another church is not found (404).
 */
class ShowServiceController extends Controller
{
    /**
     * Takes the show service action.
     */
    public function __construct(private readonly ShowServiceAction $action) {}

    /**
     * Runs ShowServiceAction for this request and returns the ServiceResource.
     */
    public function __invoke(ShowServiceRequest $request): ServiceResource
    {
        return new ServiceResource($this->action->execute(new ShowServiceDTO($request->service())));
    }
}
