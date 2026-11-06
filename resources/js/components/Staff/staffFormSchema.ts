import { z } from 'zod';
import { STAFF_ROLES, type StaffRole } from '@/constants/enums';
import { PHONE_INPUT_PATTERN } from '@/constants/validation';
import type { StaffChange, StaffInviteInput, StaffMember } from '@/types/staff';

/** Whether a text is one of the staff roles the API knows. */
export function isStaffRole(value: string): value is StaffRole {
    return STAFF_ROLES.some((role) => role.value === value);
}

const nameField = z.string().trim().min(1, 'Enter their name').max(255);
const phoneField = z.string().trim().regex(PHONE_INPUT_PATTERN, 'Enter a valid phone number');
const roleField = z.string();

/** The role is a drop-down that starts empty, so a missing choice is reported here, beside the other checks. */
function requireRole(values: { role: string }, context: z.RefinementCtx) {
    if (!isStaffRole(values.role)) {
        context.addIssue({ code: 'custom', path: ['role'], message: 'Choose a role' });
    }
}

/** The checks on the invite form. The email is made lowercase, as the server stores it. */
export const inviteStaffSchema = z
    .object({
        name: nameField,
        email: z.string().trim().toLowerCase().max(255).pipe(z.email('Enter a valid email address')),
        phone: phoneField,
        role: roleField,
    })
    .superRefine(requireRole);

/** The checks on the change form: the same, without the email, which cannot be changed here. */
export const changeStaffSchema = z.object({ name: nameField, phone: phoneField, role: roleField }).superRefine(requireRole);

export type InviteStaffValues = z.infer<typeof inviteStaffSchema>;
export type ChangeStaffValues = z.infer<typeof changeStaffSchema>;

/** An invitation starts with nothing filled in. */
export function blankInviteValues(): InviteStaffValues {
    return { name: '', email: '', phone: '', role: '' };
}

/** The change form, filled in from the staff member (they hold one role). */
export function valuesFromStaff(staff: StaffMember): ChangeStaffValues {
    return { name: staff.name, phone: staff.phone, role: staff.roles[0] ?? '' };
}

/** Turns the invite form into what the API takes. The schema has already checked the role. */
export function toStaffInviteInput(values: InviteStaffValues): StaffInviteInput | null {
    return isStaffRole(values.role) ? { name: values.name, email: values.email, phone: values.phone, role: values.role } : null;
}

/** Turns the change form into what the API takes. The schema has already checked the role. */
export function toStaffChange(values: ChangeStaffValues): StaffChange | null {
    return isStaffRole(values.role) ? { name: values.name, phone: values.phone, role: values.role } : null;
}
