/**
 * The permission names the API sends in `user.permissions`, mirrored from app/Enums/StaffPermission.
 * The screen uses them only to hide buttons; the server enforces every one (403).
 */
export const PERMISSIONS = {
    MEMBERS_VIEW: 'members.view',
    MEMBERS_EDIT: 'members.edit',
    MEMBERS_DEACTIVATE: 'members.deactivate',
    MEMBERS_EXPORT: 'members.export',
    MEMBERS_DATA_EXPORT: 'members.data-export',
    MEMBERS_ERASE: 'members.erase',
    MILESTONES_VIEW: 'milestones.view',
    GIVING_SUMMARY: 'giving.summary',
    GIVING_VIEW: 'giving.view',
    GIVING_RECORD: 'giving.record',
    GIVING_EDIT: 'giving.edit',
    GIVING_EXPORT: 'giving.export',
    SMS_SEND: 'sms.send',
    REPORTS_VIEW: 'reports.view',
    CHURCH_SETTINGS: 'church.settings',
    STAFF_MANAGE: 'staff.manage',
} as const;

export type Permission = (typeof PERMISSIONS)[keyof typeof PERMISSIONS];
