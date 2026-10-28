import type { EducationalLevel, FamilyRole, Gender, MaritalStatus, MemberStatus, MembershipType, MilestoneType } from '@/constants/enums';

/**
 * Shapes of the member, family and department endpoints (docs/08-api.md, docs/openapi.json).
 * Every enum field arrives with a `_label` beside it, ready to show.
 */

export interface PageMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface Page<T> {
    data: T[];
    meta: PageMeta;
}

export interface MemberSummary {
    id: string;
    member_number: string;
    full_name: string;
    preferred_name: string | null;
    gender: Gender;
    gender_label: string;
    phone: string;
    photo_url: string | null;
    status: MemberStatus;
    status_label: string;
    departments: string[];
    family_id: string | null;
    membership_date: string | null;
}

export interface MemberFamilyRef {
    id: string;
    name: string;
    role: FamilyRole | null;
    role_label: string | null;
}

export interface MemberDepartmentRef {
    id: string;
    name: string;
    role: string | null;
}

export interface Milestone {
    id: string;
    type: MilestoneType;
    type_label: string;
    occurred_at: string;
    officiant: string | null;
    cohort: string | null;
    notes: string | null;
}

export interface GivingSummary {
    this_year: string;
    this_year_formatted: string;
    this_month: string;
    this_month_formatted: string;
    all_time: string;
    all_time_formatted: string;
}

export interface AttendanceSummary {
    last_4_weeks: number;
    last_8_weeks: number;
    last_12_weeks: number;
}

export interface Member {
    id: string;
    member_number: string;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    preferred_name: string | null;
    gender: Gender;
    gender_label: string;
    date_of_birth: string | null;
    phone: string;
    phone_secondary: string | null;
    email: string | null;
    photo_url: string | null;
    address: string | null;
    nearest_landmark: string | null;
    digital_address: string | null;
    marital_status: MaritalStatus | null;
    marital_status_label: string | null;
    occupation: string | null;
    tribe: string | null;
    language_preference: string | null;
    educational_level: EducationalLevel | null;
    educational_level_label: string | null;
    membership_type: MembershipType;
    membership_type_label: string;
    membership_date: string | null;
    status: MemberStatus;
    status_label: string;
    status_changed_at: string | null;
    status_note: string | null;
    family?: MemberFamilyRef | null;
    departments?: MemberDepartmentRef[];
    /** Present only for roles allowed to see milestones. */
    milestones?: Milestone[];
    assigned_pastor?: { id: string; name: string } | null;
    /** Present only for roles allowed to see giving. */
    giving_summary?: GivingSummary;
    attendance_summary: AttendanceSummary;
    whatsapp_opted_in: boolean;
    sms_opted_in: boolean;
    created_at: string | null;
}

/** The directory filters. They live in the URL, so every value is optional text or a page number. */
export interface MemberFilters {
    search: string;
    status: MemberStatus | '';
    gender: Gender | '';
    department_id: string;
    page: number;
}

/** What the add and edit form sends. Empty optional fields are left out. */
export interface MemberInput {
    first_name: string;
    middle_name?: string | null;
    last_name: string;
    preferred_name?: string | null;
    gender: Gender;
    date_of_birth?: string | null;
    phone: string;
    phone_secondary?: string | null;
    email?: string | null;
    address?: string | null;
    nearest_landmark?: string | null;
    digital_address?: string | null;
    national_id_type?: string | null;
    national_id_number?: string | null;
    marital_status?: MaritalStatus | null;
    occupation?: string | null;
    tribe?: string | null;
    language_preference?: string | null;
    educational_level?: EducationalLevel | null;
    membership_type?: MembershipType;
    membership_date?: string | null;
    status?: MemberStatus;
    family_id?: string | null;
    family_role?: FamilyRole | null;
    department_ids?: string[];
    whatsapp_opted_in?: boolean;
    sms_opted_in?: boolean;
}

export interface MilestoneInput {
    type: MilestoneType;
    occurred_at: string;
    officiant?: string | null;
    cohort?: string | null;
    notes?: string | null;
}

export interface Family {
    id: string;
    name: string;
    notes: string | null;
    head_member_id: string | null;
    members_count?: number;
    members?: FamilyMember[];
}

export interface FamilyMember {
    id: string;
    member_number: string;
    full_name: string;
    gender: Gender;
    gender_label: string;
    status: MemberStatus;
    status_label: string;
    family_role: FamilyRole | null;
    family_role_label: string | null;
}

export interface Department {
    id: string;
    name: string;
    description: string | null;
    head_user_id: string | null;
    is_active: boolean;
    members_count?: number;
}
