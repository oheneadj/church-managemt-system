<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ConsentPurpose;
use App\Models\Member;
use App\Models\User;
use App\Repositories\ConsentRepositoryInterface;

/**
 * Keeps the consent history in step with a member's message choices (agreement section 36): when a choice is
 * switched on an agreement is recorded with the time and the privacy policy version, and when it is switched off the
 * open agreement is marked withdrawn. It opens no transaction: the caller's does.
 */
class ConsentRecorder
{
    /**
     * Takes the consent repository.
     */
    public function __construct(private readonly ConsentRepositoryInterface $consents) {}

    /**
     * Records or withdraws agreements so each purpose matches the member's current choice.
     */
    public function sync(Member $member, ?User $recordedBy): void
    {
        foreach (ConsentPurpose::cases() as $purpose) {
            $open = $this->consents->openFor($member, $purpose);
            $agreed = $this->agreed($member, $purpose);

            if ($agreed && $open === null) {
                $this->consents->create([
                    'church_id' => $member->church_id,
                    'member_id' => $member->id,
                    'purpose' => $purpose,
                    'consented_at' => now(),
                    'policy_version' => config('privacy.policy_version'),
                    'recorded_by' => $recordedBy?->id,
                ]);
            }

            if (! $agreed && $open !== null) {
                $this->consents->withdraw($open);
            }
        }
    }

    /**
     * Whether the member's own record says they agreed to this purpose.
     */
    private function agreed(Member $member, ConsentPurpose $purpose): bool
    {
        return match ($purpose) {
            ConsentPurpose::Sms => $member->sms_opted_in,
            ConsentPurpose::Whatsapp => $member->whatsapp_opted_in,
        };
    }
}
