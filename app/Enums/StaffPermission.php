<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Every permission a staff role can carry. The value is the permission name stored by Spatie Permission.
 */
enum StaffPermission: string
{
    case MembersView = 'members.view';
    case MembersEdit = 'members.edit';
    case MembersDeactivate = 'members.deactivate';
    case MembersExport = 'members.export';
    case MembersErase = 'members.erase';
    case MilestonesView = 'milestones.view';
    case GivingSummary = 'giving.summary';
    case GivingRecord = 'giving.record';
    case GivingEdit = 'giving.edit';
    case GivingExport = 'giving.export';
    case SmsSend = 'sms.send';
    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';
    case ChurchSettings = 'church.settings';
    case StaffManage = 'staff.manage';
    case FundTypesManage = 'fund-types.manage';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::MembersView => 'View members',
            self::MembersEdit => 'Add and edit members',
            self::MembersDeactivate => 'Deactivate members',
            self::MembersExport => 'Export members',
            self::MembersErase => 'Erase a member\'s personal data',
            self::MilestonesView => 'View member milestones',
            self::GivingSummary => 'View giving summaries',
            self::GivingRecord => 'Record giving',
            self::GivingEdit => 'Void and correct giving',
            self::GivingExport => 'Export giving',
            self::SmsSend => 'Send messages',
            self::ReportsView => 'View reports',
            self::ReportsExport => 'Export reports',
            self::ChurchSettings => 'Change church settings',
            self::StaffManage => 'Manage staff',
            self::FundTypesManage => 'Manage fund types',
        };
    }
}
