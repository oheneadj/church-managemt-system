<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Services;

use App\Actions\Services\SetServiceCheckinAction;
use App\DTOs\Services\SetServiceCheckinDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Services\SetServiceCheckinRequest;
use App\Http\Resources\ServiceResource;

/**
 * Opens or closes a service for check-in (docs/08, Open / Close Service for Check-in).
 */
class SetServiceCheckinController extends Controller
{
    /**
     * Takes the set service check-in action.
     */
    public function __construct(private readonly SetServiceCheckinAction $action) {}

    /**
     * Runs SetServiceCheckinAction for this request and returns the ServiceResource.
     */
    public function __invoke(SetServiceCheckinRequest $request): ServiceResource
    {
        return new ServiceResource($this->action->execute(SetServiceCheckinDTO::fromRequest($request)));
    }
}
