<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\RecipientType;

/**
 * Everything the SMS job needs. `logContent` is what gets stored in message_logs,
 * so a message containing a secret (an OTP) can be recorded without its secret.
 */
final readonly class OutboundSms
{
    public function __construct(
        public string $churchId,
        public string $phone,
        public string $message,
        public string $messageType,
        public RecipientType $recipientType,
        public ?string $recipientId = null,
        public ?string $logContent = null,
    ) {}
}
