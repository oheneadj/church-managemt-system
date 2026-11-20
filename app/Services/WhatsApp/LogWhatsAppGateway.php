<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppGateway;
use App\DTOs\WhatsApp\OutboundWhatsApp;
use App\DTOs\WhatsApp\WhatsAppReceipt;
use Illuminate\Support\Facades\Log;

/**
 * Development-only gateway that writes messages to the log instead of sending them, used until Meta approves the
 * WhatsApp account. The text and number are only logged in the local environment, because they are personal data.
 */
final class LogWhatsAppGateway implements WhatsAppGateway
{
    /**
     * Writes the message to the log instead of sending it. For development only.
     */
    public function send(OutboundWhatsApp $message): WhatsAppReceipt
    {
        if (app()->environment('local')) {
            Log::info('WhatsApp (local log driver)', ['to' => $message->phone, 'message' => $message->text]);

            return new WhatsAppReceipt;
        }

        Log::info('WhatsApp message accepted by the log driver; content is not recorded outside local.');

        return new WhatsAppReceipt;
    }
}
