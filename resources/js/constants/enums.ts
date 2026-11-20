/**
 * The fixed sets of values the API uses, mirrored from app/Enums so the app has no magic strings.
 * Each list gives the value sent to the API and the label shown on screen. A backend test
 * (tests/Feature/Operations/FrontendEnumsTest.php) fails if a list here drifts from its PHP enum.
 */

export interface Option<V extends string> {
    value: V;
    label: string;
}

export const GENDERS = [
    { value: 'male', label: 'Male' },
    { value: 'female', label: 'Female' },
] as const satisfies readonly Option<string>[];

export const MARITAL_STATUSES = [
    { value: 'single', label: 'Single' },
    { value: 'married', label: 'Married' },
    { value: 'widowed', label: 'Widowed' },
    { value: 'divorced', label: 'Divorced' },
] as const satisfies readonly Option<string>[];

export const MEMBERSHIP_TYPES = [
    { value: 'full', label: 'Full' },
    { value: 'associate', label: 'Associate' },
    { value: 'affiliate', label: 'Affiliate' },
] as const satisfies readonly Option<string>[];

export const MEMBER_STATUSES = [
    { value: 'prospect', label: 'Prospect' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'transferred', label: 'Transferred' },
    { value: 'deceased', label: 'Deceased' },
    { value: 'archived', label: 'Archived' },
] as const satisfies readonly Option<string>[];

export const FAMILY_ROLES = [
    { value: 'head', label: 'Head' },
    { value: 'spouse', label: 'Spouse' },
    { value: 'child', label: 'Child' },
    { value: 'dependent', label: 'Dependent' },
] as const satisfies readonly Option<string>[];

export const MILESTONE_TYPES = [
    { value: 'dedication', label: 'Dedication' },
    { value: 'baptism', label: 'Baptism' },
    { value: 'confirmation', label: 'Confirmation' },
    { value: 'first_communion', label: 'First communion' },
    { value: 'membership_class', label: 'Membership class' },
] as const satisfies readonly Option<string>[];

export const EDUCATIONAL_LEVELS = [
    { value: 'creche', label: 'Creche' },
    { value: 'kg', label: 'KG' },
    { value: 'primary_1', label: 'Primary 1' },
    { value: 'primary_2', label: 'Primary 2' },
    { value: 'primary_3', label: 'Primary 3' },
    { value: 'primary_4', label: 'Primary 4' },
    { value: 'primary_5', label: 'Primary 5' },
    { value: 'primary_6', label: 'Primary 6' },
    { value: 'jhs_1', label: 'JHS 1' },
    { value: 'jhs_2', label: 'JHS 2' },
    { value: 'jhs_3', label: 'JHS 3' },
    { value: 'shs_1', label: 'SHS 1' },
    { value: 'shs_2', label: 'SHS 2' },
    { value: 'shs_3', label: 'SHS 3' },
    { value: 'tertiary', label: 'Tertiary' },
    { value: 'graduate', label: 'Graduate' },
] as const satisfies readonly Option<string>[];

/** Statuses that take a member out of active church life. Moving to one needs the deactivate permission and a confirmation. */
export const LEAVING_STATUSES: readonly MemberStatus[] = ['inactive', 'transferred', 'deceased', 'archived'];

/** The statuses a new member can start with; the others are reached by changing status later. */
export const NEW_MEMBER_STATUSES = MEMBER_STATUSES.filter((status) => status.value === 'prospect' || status.value === 'active');

export type Gender = (typeof GENDERS)[number]['value'];
export type MaritalStatus = (typeof MARITAL_STATUSES)[number]['value'];
export type MembershipType = (typeof MEMBERSHIP_TYPES)[number]['value'];
export type MemberStatus = (typeof MEMBER_STATUSES)[number]['value'];
export type FamilyRole = (typeof FAMILY_ROLES)[number]['value'];
export type MilestoneType = (typeof MILESTONE_TYPES)[number]['value'];
export type EducationalLevel = (typeof EDUCATIONAL_LEVELS)[number]['value'];

export const SERVICE_TYPES = [
    { value: 'sunday', label: 'Sunday' },
    { value: 'midweek', label: 'Midweek' },
    { value: 'special', label: 'Special' },
    { value: 'youth', label: 'Youth' },
    { value: 'children', label: 'Children' },
] as const satisfies readonly Option<string>[];

export const WEEKDAYS = [
    { value: 'sunday', label: 'Sunday' },
    { value: 'monday', label: 'Monday' },
    { value: 'tuesday', label: 'Tuesday' },
    { value: 'wednesday', label: 'Wednesday' },
    { value: 'thursday', label: 'Thursday' },
    { value: 'friday', label: 'Friday' },
    { value: 'saturday', label: 'Saturday' },
] as const satisfies readonly Option<string>[];

export const PAYMENT_METHODS = [
    { value: 'momo', label: 'Mobile Money' },
    { value: 'card', label: 'Card' },
    { value: 'cash', label: 'Cash' },
    { value: 'ussd', label: 'USSD' },
] as const satisfies readonly Option<string>[];

export const CHECKIN_METHODS = [
    { value: 'manual', label: 'Manual' },
    { value: 'whatsapp', label: 'WhatsApp' },
    { value: 'qr', label: 'QR code' },
    { value: 'biometric', label: 'Biometric' },
] as const satisfies readonly Option<string>[];

export const PLEDGE_STATUSES = [
    { value: 'active', label: 'Active' },
    { value: 'fulfilled', label: 'Fulfilled' },
    { value: 'partial', label: 'Partial' },
    { value: 'lapsed', label: 'Lapsed' },
] as const satisfies readonly Option<string>[];

export const STAFF_ROLES = [
    { value: 'church-admin', label: 'Church admin' },
    { value: 'pastor', label: 'Pastor' },
    { value: 'finance-secretary', label: 'Finance secretary' },
    { value: 'department-head', label: 'Department head' },
    { value: 'youth-pastor', label: 'Youth pastor' },
] as const satisfies readonly Option<string>[];

export const STAFF_PERMISSIONS = [
    { value: 'members.view', label: 'View members' },
    { value: 'members.edit', label: 'Add and edit members' },
    { value: 'members.deactivate', label: 'Deactivate members' },
    { value: 'members.export', label: 'Export the member list' },
    { value: 'members.data-export', label: "Export one member's full personal record" },
    { value: 'members.erase', label: "Erase a member's personal data" },
    { value: 'milestones.view', label: 'View member milestones' },
    { value: 'giving.summary', label: 'View giving summaries' },
    { value: 'giving.view', label: 'View individual gifts' },
    { value: 'giving.record', label: 'Record giving' },
    { value: 'giving.edit', label: 'Void and correct giving' },
    { value: 'giving.export', label: 'Export giving' },
    { value: 'sms.send', label: 'Send messages' },
    { value: 'reports.view', label: 'View reports' },
    { value: 'reports.export', label: 'Export reports' },
    { value: 'church.settings', label: 'Change church settings' },
    { value: 'staff.manage', label: 'Manage staff' },
    { value: 'fund-types.manage', label: 'Manage fund types' },
] as const satisfies readonly Option<string>[];

export type ServiceType = (typeof SERVICE_TYPES)[number]['value'];
export type Weekday = (typeof WEEKDAYS)[number]['value'];
export type PaymentMethod = (typeof PAYMENT_METHODS)[number]['value'];
export type StaffRole = (typeof STAFF_ROLES)[number]['value'];
export type PledgeStatus = (typeof PLEDGE_STATUSES)[number]['value'];
export type CheckInMethod = (typeof CHECKIN_METHODS)[number]['value'];

export const MESSAGE_CHANNELS = [
    { value: 'sms', label: 'SMS' },
    { value: 'whatsapp', label: 'WhatsApp' },
    { value: 'email', label: 'Email' },
] as const satisfies readonly Option<string>[];

export const MESSAGE_STATUSES = [
    { value: 'queued', label: 'Queued' },
    { value: 'sent', label: 'Sent' },
    { value: 'delivered', label: 'Delivered' },
    { value: 'failed', label: 'Failed' },
] as const satisfies readonly Option<string>[];

export const REPORT_GROUPINGS = [
    { value: 'week', label: 'By week' },
    { value: 'service', label: 'By service' },
] as const satisfies readonly Option<string>[];

export type ReportGrouping = (typeof REPORT_GROUPINGS)[number]['value'];

export type MessageStatus = (typeof MESSAGE_STATUSES)[number]['value'];
