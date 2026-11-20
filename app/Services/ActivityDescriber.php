<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Activity\ActivityItem;
use App\Enums\ActivityKind;
use App\Enums\MemberStatus;
use App\Models\AuditLog;
use App\Models\Contribution;
use App\Models\Member;
use App\Models\Pledge;
use App\Models\User;
use App\ValueObjects\Money;

/**
 * Turns one line of the change history into the plain sentence the activity feed shows (D-068). The sentence holds only what
 * it says: a name, an amount and a fund. It never holds a reason, a note, an email, a phone number or the old and new values,
 * and a person whose data was erased is "A member", never the erased marker.
 */
class ActivityDescriber
{
    /**
     * Describes a line the feed shows. The caller has already chosen only the kinds the person may see.
     */
    public function describe(AuditLog $line, ActivityKind $kind): ActivityItem
    {
        $subject = $line->auditable;
        $text = match ($kind) {
            ActivityKind::MemberAdded => __('activity.member_added', ['name' => $this->memberName($subject)]),
            ActivityKind::MemberStatusChanged => $this->statusChanged($line, $subject),
            ActivityKind::GiftRecorded, ActivityKind::GiftVoided => $this->gift($kind, $subject),
            ActivityKind::PledgeMade => $this->pledge($subject),
            ActivityKind::StaffInvited => __('activity.staff_invited', ['name' => $this->staffName($subject)]),
            ActivityKind::StaffStatusChanged => __(($line->new_values['is_active'] ?? false) === true ? 'activity.staff_reactivated' : 'activity.staff_deactivated', ['name' => $this->staffName($subject)]),
            ActivityKind::SettingsChanged => __('activity.settings_changed'),
        };

        return new ActivityItem($line->id, $kind, $text, $line->actor_name, $line->created_at);
    }

    /**
     * The line's kind, or null when the feed does not show it.
     */
    public function kindOf(AuditLog $line): ?ActivityKind
    {
        return ActivityKind::forLine($line->auditable_type, $line->event);
    }

    /**
     * The member's name, or "A member" when the record is gone or its data was erased.
     */
    private function memberName(mixed $subject): string
    {
        if (! $subject instanceof Member || $subject->first_name === config('privacy.erased_marker')) {
            return __('activity.a_member');
        }

        return $subject->full_name;
    }

    /**
     * The staff member's name, or "A staff member" when the record is gone.
     */
    private function staffName(mixed $subject): string
    {
        return $subject instanceof User ? $subject->name : __('activity.a_staff_member');
    }

    /**
     * "Kwame Boateng is now Inactive." or, when the new status is not readable (an erased member), a plainer sentence.
     */
    private function statusChanged(AuditLog $line, mixed $subject): string
    {
        $status = MemberStatus::tryFrom((string) ($line->new_values['status'] ?? ''));

        return $status === null
            ? __('activity.member_status_changed_unknown', ['name' => $this->memberName($subject)])
            : __('activity.member_status_changed', ['name' => $this->memberName($subject), 'status' => $status->label()]);
    }

    /**
     * "A gift of GHS 1,250.50 to Building Fund was recorded." The amount and fund come from the gift as it was recorded. No
     * member's name is part of it, and the void reason is never shown.
     */
    private function gift(ActivityKind $kind, mixed $subject): string
    {
        if (! $subject instanceof Contribution) {
            return __('activity.gift_unknown', ['what' => $kind === ActivityKind::GiftVoided ? 'voided' : 'recorded']);
        }

        return __('activity.'.$kind->value, ['amount' => Money::ghs($subject->amount)->formatted(), 'fund' => $subject->fund_name]);
    }

    /**
     * "A pledge of GHS 3,000.00 to Building Fund was made."
     */
    private function pledge(mixed $subject): string
    {
        if (! $subject instanceof Pledge) {
            return __('activity.pledge_made', ['amount' => '', 'fund' => __('activity.a_fund_that_was_removed')]);
        }

        return __('activity.pledge_made', ['amount' => Money::ghs($subject->amount_pledged)->formatted(), 'fund' => $subject->fundType->name ?? __('activity.a_fund_that_was_removed')]);
    }
}
