<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Messages\BroadcastAudience;
use App\DTOs\Messages\BroadcastEstimate;
use App\Models\Church;
use App\Repositories\MemberRepositoryInterface;

/**
 * Works out what a broadcast would reach and cost (docs/08, Send Broadcast; QA-08 TC-08-08). One credit is one part of the
 * text for each person (D-051), counted by `SmsSegmenter`, the same count the sender uses. The estimate reads and changes
 * nothing, and it is the one place the question is answered, for the preview and for the real send.
 */
class BroadcastEstimator
{
    /**
     * Takes the member repository and the text segmenter.
     */
    public function __construct(private readonly MemberRepositoryInterface $members, private readonly SmsSegmenter $segmenter) {}

    /**
     * How many people the audience is, how many parts the text is and what that costs, against the church's balance.
     */
    public function estimate(Church $church, string $message, BroadcastAudience $audience): BroadcastEstimate
    {
        $recipients = $this->members->countBroadcastRecipients($audience);
        $parts = $this->segmenter->parts($message);

        return new BroadcastEstimate($recipients, $parts, $recipients * $parts, $church->sms_credits);
    }
}
