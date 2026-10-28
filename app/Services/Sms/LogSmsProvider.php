<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Contracts\SmsProvider;
use App\DTOs\OutboundSms;
use App\DTOs\SmsReceipt;
use Illuminate\Support\Facades\Log;

/**
 * Development-only provider that writes messages to the log instead of sending them.
 * I only include the message text in the local environment, because OTP codes and phone
 * numbers must never reach log files anywhere else.
 */
final class LogSmsProvider implements SmsProvider
{
    /**
     * Writes the message to the log instead of sending it. For development only.
     */
    public function send(OutboundSms $sms, string $idempotencyKey): SmsReceipt
    {
        if (app()->environment('local')) {
            Log::info('SMS (local log driver)', ['to' => $sms->phone, 'message' => $sms->message]);

            return new SmsReceipt;
        }

        Log::info('SMS accepted by the log driver; content is not recorded outside local.');

        return new SmsReceipt;
    }
}
