<?php

declare(strict_types=1);

namespace App\Http\Requests\Messages;

/**
 * Validates the question "who would this broadcast reach and what would it cost?". The same fields as sending, with nothing sent.
 */
class EstimateBroadcastRequest extends BroadcastInputRequest {}
