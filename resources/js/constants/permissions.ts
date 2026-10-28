/**
 * The permission names the API sends in `user.permissions`, mirrored from app/Enums/StaffPermission.
 * The screen uses them only to hide buttons; the server enforces every one (403).
 */
export const PERMISSIONS = {
    MEMBERS_VIEW: 'members.view',
    MEMBERS_EDIT: 'members.edit',
    MEMBERS_DEACTIVATE: 'members.deactivate',
    MILESTONES_VIEW: 'milestones.view',
    GIVING_SUMMARY: 'giving.summary',
} as const;

export type Permission = (typeof PERMISSIONS)[keyof typeof PERMISSIONS];
