<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The five staff roles (docs/06). The value is the role name stored by Spatie Permission.
 */
enum StaffRole: string
{
    case ChurchAdmin = 'church-admin';
    case Pastor = 'pastor';
    case FinanceSecretary = 'finance-secretary';
    case DepartmentHead = 'department-head';
    case YouthPastor = 'youth-pastor';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::ChurchAdmin => 'Church admin',
            self::Pastor => 'Pastor',
            self::FinanceSecretary => 'Finance secretary',
            self::DepartmentHead => 'Department head',
            self::YouthPastor => 'Youth pastor',
        };
    }

    /**
     * The permissions this role carries. The church admin carries all of them.
     *
     * @return list<StaffPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::ChurchAdmin => StaffPermission::cases(),
            self::Pastor => [StaffPermission::MembersView, StaffPermission::MembersEdit, StaffPermission::MilestonesView, StaffPermission::GivingSummary, StaffPermission::ReportsView, StaffPermission::SmsSend],
            self::FinanceSecretary => [StaffPermission::MembersView, StaffPermission::GivingSummary, StaffPermission::GivingView, StaffPermission::GivingRecord, StaffPermission::GivingEdit, StaffPermission::GivingExport, StaffPermission::ReportsView, StaffPermission::ReportsExport],
            self::DepartmentHead => [StaffPermission::MembersView],
            self::YouthPastor => [StaffPermission::MembersView, StaffPermission::MilestonesView],
        };
    }
}
