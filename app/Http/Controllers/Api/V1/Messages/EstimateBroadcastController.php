<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Messages;

use App\Actions\Messages\EstimateBroadcastAction;
use App\DTOs\Messages\EstimateBroadcastDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Messages\EstimateBroadcastRequest;
use App\Http\Resources\BroadcastEstimateResource;

/**
 * Says who a broadcast would reach and what it would cost, before anything is sent (docs/08, Send Broadcast).
 */
class EstimateBroadcastController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly EstimateBroadcastAction $action) {}

    /**
     * Runs EstimateBroadcastAction for this request and returns the BroadcastEstimateResource.
     */
    public function __invoke(EstimateBroadcastRequest $request): BroadcastEstimateResource
    {
        return new BroadcastEstimateResource($this->action->execute(EstimateBroadcastDTO::fromRequest($request)));
    }
}
