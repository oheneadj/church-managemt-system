<?php

declare(strict_types=1);

namespace App\DTOs\WhatsApp;

use App\Enums\CheckinReply;
use App\Models\Member;

/**
 * What came of one CHECKIN message: which answer to send and, when the sender was matched, who they are.
 */
final readonly class CheckinOutcome
{
    /**
     * Holds the answer, the words to put in it and the matched member (null for an unknown number).
     *
     * @param  array<string, string>  $values
     */
    public function __construct(public CheckinReply $reply, public array $values = [], public ?Member $member = null) {}
}
