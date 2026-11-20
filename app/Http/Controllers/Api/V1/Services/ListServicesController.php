<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Services;

use App\Actions\Services\ListServicesAction;
use App\DTOs\Services\ListServicesDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Services\ListServicesRequest;
use App\Http\Resources\ServiceCollection;

/**
 * One page of services, newest first (docs/08, List Services).
 */
class ListServicesController extends Controller
{
    /**
     * Takes the list services action.
     */
    public function __construct(private readonly ListServicesAction $action) {}

    /**
     * Runs ListServicesAction for this request and returns the ServiceCollection.
     */
    public function __invoke(ListServicesRequest $request): ServiceCollection
    {
        return new ServiceCollection($this->action->execute(ListServicesDTO::fromRequest($request)));
    }
}
