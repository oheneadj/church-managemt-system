<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Services;

use App\Actions\Services\UpdateServiceAction;
use App\DTOs\Services\UpdateServiceDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Services\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;

/**
 * Changes a service (docs/08, Update Service).
 */
class UpdateServiceController extends Controller
{
    /**
     * Takes the update service action.
     */
    public function __construct(private readonly UpdateServiceAction $action) {}

    /**
     * Runs UpdateServiceAction for this request and returns the ServiceResource.
     */
    public function __invoke(UpdateServiceRequest $request): ServiceResource
    {
        return new ServiceResource($this->action->execute(UpdateServiceDTO::fromRequest($request)));
    }
}
