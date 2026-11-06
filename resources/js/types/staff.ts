/**
 * Shapes of the staff endpoints (docs/08-api.md, "Staff").
 */
import type { StaffRole } from '@/constants/enums';

/** A staff member as the church admin sees them. The API never sends a password. */
export interface StaffMember {
    id: string;
    name: string;
    email: string;
    /** In international form, for example +233244123456. */
    phone: string;
    /** The one role they hold (the API sends a list). */
    roles: string[];
    /** Permissions granted on top of the role. */
    extra_permissions: string[];
    is_active: boolean;
    /** When they last signed in, or null if they never have. */
    last_login_at: string | null;
}

/** What the invite form sends. */
export interface StaffInviteInput {
    name: string;
    email: string;
    phone: string;
    role: StaffRole;
}

/** What the change form sends: any of these; the email cannot be changed here. */
export interface StaffChange {
    name: string;
    phone: string;
    role: StaffRole;
}
