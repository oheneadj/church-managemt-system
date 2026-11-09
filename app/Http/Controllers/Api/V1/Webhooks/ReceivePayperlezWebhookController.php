<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Actions\Payments\ReceivePayperlezWebhookAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Webhooks\ReceivePayperlezWebhookRequest;
use Illuminate\Http\JsonResponse;

/**
 * Receives what PayPerlez posts about collections and payouts (docs/08, PayPerlez Webhook).
 */
class ReceivePayperlezWebhookController extends Controller
{
    /**
     * Takes the receive action.
     */
    public function __construct(private readonly ReceivePayperlezWebhookAction $action) {}

    /**
     * Hands the payload to ReceivePayperlezWebhookAction and answers 200 at once, a repeat included.
     */
    public function __invoke(ReceivePayperlezWebhookRequest $request): JsonResponse
    {
        $this->action->execute($request->rawPayload());

        return response()->json(['status' => 'ok']);
    }
}
