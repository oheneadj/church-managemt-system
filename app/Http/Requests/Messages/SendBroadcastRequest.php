<?php

declare(strict_types=1);

namespace App\Http\Requests\Messages;

/**
 * Validates a broadcast to send: the same fields as the question "what would this cost?", which it follows exactly.
 */
class SendBroadcastRequest extends BroadcastInputRequest {}
