<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Actions\WhatsApp\ReceiveWhatsAppWebhookAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Webhooks\ReceiveWhatsAppWebhookRequest;
use Illuminate\Http\JsonResponse;

/**
 * Receives what Meta posts: WhatsApp messages from members and delivery receipts (docs/08, Meta / WhatsApp Webhook).
 */
class ReceiveWhatsAppWebhookController extends Controller
{
    /**
     * Takes the receive action.
     */
    public function __construct(private readonly ReceiveWhatsAppWebhookAction $action) {}

    /**
     * Hands the payload to ReceiveWhatsAppWebhookAction and answers 200 at once, as Meta requires.
     */
    public function __invoke(ReceiveWhatsAppWebhookRequest $request): JsonResponse
    {
        $this->action->execute($request->rawPayload());

        return response()->json(['status' => 'ok']);
    }
}
