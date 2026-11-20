<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\QueueName;
use App\Enums\RecipientType;
use Illuminate\Mail\Mailable;

/**
 * Everything the email job needs. `logContent` is what is stored in message_logs, so an email that carries a secret (a
 * sign-in code) is recorded without it. `churchId` is null only for a platform email sent before a church exists. A
 * queued job has no request, so the church travels with it. `queue` is chosen by the caller: codes go on `critical`.
 */
final readonly class OutboundEmail
{
    /**
     * Holds everything the email job needs, including the church id (or null for a platform email).
     */
    public function __construct(
        public ?string $churchId,
        public string $email,
        public Mailable $mailable,
        public string $messageType,
        public RecipientType $recipientType,
        public string $logContent,
        public ?string $recipientId = null,
        public QueueName $queue = QueueName::Notifications,
    ) {}
}
