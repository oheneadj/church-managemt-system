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
    /** Permissions the role already gives. They cannot be taken away here: change the role instead. */
    role_permissions: string[];
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

/** What the permissions call sends: the extras to add and the extras to take away. At least one list is needed. */
export interface StaffPermissionChange {
    grant?: string[];
    revoke?: string[];
}

/** A staff member as the directory shows them: just who they are, so one can be chosen (for example as a department head). */
export interface StaffName {
    id: string;
    name: string;
}
