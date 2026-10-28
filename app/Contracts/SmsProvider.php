<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Sends one text message. Implementations live in App\Services\Sms and are chosen by config,
 * so no job or service ever names a specific provider.
 */
interface SmsProvider
{
    public function send(string $phone, string $message): void;
}
