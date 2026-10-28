<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\OutboundSms;
use App\DTOs\SmsReceipt;

/**
 * Sends one text message. Implementations live in App\Services\Sms and are chosen by config,
 * so no job or service ever names a specific provider.
 */
interface SmsProvider
{
    /**
     * Sends one text message and returns the provider's receipt. `$idempotencyKey` is the same for every retry of
     * one message, so a provider that supports it never sends the message twice. Implementations translate
     * failures into exceptions: a temporary failure may be retried, a SmsRejectedException may not.
     */
    public function send(OutboundSms $sms, string $idempotencyKey): SmsReceipt;
}
