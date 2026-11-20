<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Webhooks\VerifyWhatsAppWebhookRequest;
use Illuminate\Http\Response;

/**
 * Answers Meta's check that this webhook address is ours (docs/08, Meta / WhatsApp Webhook).
 */
class VerifyWhatsAppWebhookController extends Controller
{
    /**
     * Echoes Meta's challenge as plain text. The request class has already checked the verify token.
     */
    public function __invoke(VerifyWhatsAppWebhookRequest $request): Response
    {
        return response($request->challenge(), Response::HTTP_OK, ['Content-Type' => 'text/plain']);
    }
}
