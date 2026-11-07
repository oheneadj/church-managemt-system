<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Church;
use App\Models\Contribution;
use App\Models\Member;
use App\Models\Pledge;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The kinds of change shown in the recent activity feed (D-068). Each is one kind of line in the change history: what it is
 * about and what happened to it. A kind also names the permission that guards the thing it is about, so the feed never tells
 * a person something they could not look up themselves. Anything not listed here (member edits, record views, exports,
 * erasures, role changes) is left out of the feed on purpose.
 */
enum ActivityKind: string
{
    case MemberAdded = 'member_added';
    case MemberStatusChanged = 'member_status_changed';
    case GiftRecorded = 'gift_recorded';
    case GiftVoided = 'gift_voided';
    case PledgeMade = 'pledge_made';
    case StaffInvited = 'staff_invited';
    case StaffStatusChanged = 'staff_status_changed';
    case SettingsChanged = 'settings_changed';

    /**
     * The record this kind of line is about, as its model's morph class (how the history stores it).
     *
     * @return class-string<Model>
     */
    public function subject(): string
    {
        return match ($this) {
            self::MemberAdded, self::MemberStatusChanged => Member::class,
            self::GiftRecorded, self::GiftVoided => Contribution::class,
            self::PledgeMade => Pledge::class,
            self::StaffInvited, self::StaffStatusChanged => User::class,
            self::SettingsChanged => Church::class,
        };
    }

    /**
     * What happened to that record.
     */
    public function event(): AuditEvent
    {
        return match ($this) {
            self::MemberAdded, self::GiftRecorded, self::PledgeMade, self::StaffInvited => AuditEvent::Created,
            self::MemberStatusChanged, self::StaffStatusChanged => AuditEvent::StatusChanged,
            self::GiftVoided => AuditEvent::Voided,
            self::SettingsChanged => AuditEvent::Updated,
        };
    }

    /**
     * The permission a person needs, as well as `reports.view`, to see this kind of line.
     */
    public function permission(): StaffPermission
    {
        return match ($this) {
            self::MemberAdded, self::MemberStatusChanged => StaffPermission::MembersView,
            self::GiftRecorded, self::GiftVoided, self::PledgeMade => StaffPermission::GivingView,
            self::StaffInvited, self::StaffStatusChanged => StaffPermission::StaffManage,
            self::SettingsChanged => StaffPermission::ChurchSettings,
        };
    }

    /**
     * The kind a line of the change history is, or null when the feed does not show it.
     */
    public static function forLine(string $subjectType, AuditEvent $event): ?self
    {
        foreach (self::cases() as $kind) {
            if ($kind->subject() === $subjectType && $kind->event() === $event) {
                return $kind;
            }
        }

        return null;
    }

    /**
     * The kinds this person may see: those whose permission they hold.
     *
     * @return list<self>
     */
    public static function availableTo(User $user): array
    {
        return array_values(array_filter(self::cases(), fn (self $kind): bool => $user->hasPermissionTo($kind->permission())));
    }
}
