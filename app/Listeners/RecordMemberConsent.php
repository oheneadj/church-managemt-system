<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MemberCreated;
use App\Events\MemberErased;
use App\Events\MemberUpdated;
use App\Services\ConsentRecorder;

/**
 * Keeps the consent history right after a member is added, changed or erased. Like the change log it is not queued:
 * the record must be saved in the same transaction as the change, so a rolled-back change leaves no consent behind.
 */
class RecordMemberConsent
{
    /**
     * Takes the consent recorder.
     */
    public function __construct(private readonly ConsentRecorder $consents) {}

    /**
     * Records the agreements a new member starts with.
     */
    public function handleCreated(MemberCreated $event): void
    {
        $this->consents->sync($event->member, $event->actor);
    }

    /**
     * Records a change in the member's message choices.
     */
    public function handleUpdated(MemberUpdated $event): void
    {
        $this->consents->sync($event->member, $event->actor);
    }

    /**
     * Withdraws every agreement when the member is erased (their choices were switched off by the erasure).
     */
    public function handleErased(MemberErased $event): void
    {
        $this->consents->sync($event->member, $event->actor);
    }
}
