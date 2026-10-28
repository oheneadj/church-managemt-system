<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Services;

use App\Actions\Services\CreateServiceAction;
use App\DTOs\Services\CreateServiceDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Services\StoreServiceRequest;
use App\Http\Resources\ServiceResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Creates a service with a check-in code made by the system (docs/08, Create Service).
 */
class StoreServiceController extends Controller
{
    /**
     * Takes the create service action.
     */
    public function __construct(private readonly CreateServiceAction $action) {}

    /**
     * Runs CreateServiceAction for this request and returns the ServiceResource.
     */
    public function __invoke(StoreServiceRequest $request): JsonResponse
    {
        return (new ServiceResource($this->action->execute(CreateServiceDTO::fromRequest($request))))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
