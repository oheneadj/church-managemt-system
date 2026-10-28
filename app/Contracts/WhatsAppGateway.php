<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\WhatsApp\OutboundWhatsApp;
use App\DTOs\WhatsApp\WhatsAppReceipt;

/**
 * Sends one WhatsApp message. Implementations live in App\Services\WhatsApp and are chosen by config, so no job
 * or action ever names Meta. Swapping the provider is a config change and one new class (agreement section 15).
 */
interface WhatsAppGateway
{
    /**
     * Sends one text message and returns the provider's receipt. Implementations turn failures into exceptions:
     * a temporary failure may be retried, a WhatsAppRejectedException may not.
     */
    public function send(OutboundWhatsApp $message): WhatsAppReceipt;
}
