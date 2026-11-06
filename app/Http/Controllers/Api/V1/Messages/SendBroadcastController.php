<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Messages;

use App\Actions\Messages\SendBroadcastAction;
use App\DTOs\Messages\SendBroadcastDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Messages\SendBroadcastRequest;
use App\Http\Resources\BroadcastQueuedResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a broadcast to many members (docs/08, Send Broadcast). The answer is 202: a worker turns it into texts. Needs an
 * Idempotency-Key header, so a double click sends one broadcast.
 */
class SendBroadcastController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly SendBroadcastAction $action) {}

    /**
     * Runs SendBroadcastAction for this request and returns the JsonResponse.
     */
    public function __invoke(SendBroadcastRequest $request): JsonResponse
    {
        $queued = $this->action->execute(SendBroadcastDTO::fromRequest($request));

        return (new BroadcastQueuedResource($queued))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
