import type { Family, Member, MemberSummary, Page } from '@/types/members';

/** A directory row, with sensible defaults a test can override. */
export function memberSummary(overrides: Partial<MemberSummary> = {}): MemberSummary {
    return {
        id: 'member-1',
        member_number: 'GRC-0001',
        full_name: 'Emmanuel Mensah-Boateng',
        preferred_name: null,
        gender: 'male',
        gender_label: 'Male',
        phone: '+233244123456',
        photo_url: null,
        status: 'active',
        status_label: 'Active',
        departments: [],
        family_id: null,
        membership_date: null,
        ...overrides,
    };
}

/** A full member record, with sensible defaults a test can override. */
export function member(overrides: Partial<Member> = {}): Member {
    return {
        id: 'member-1',
        member_number: 'GRC-0001',
        first_name: 'Emmanuel',
        middle_name: null,
        last_name: 'Mensah-Boateng',
        preferred_name: null,
        gender: 'male',
        gender_label: 'Male',
        date_of_birth: '1985-07-12',
        phone: '+233244123456',
        phone_secondary: null,
        email: null,
        photo_url: null,
        address: null,
        nearest_landmark: null,
        digital_address: null,
        marital_status: null,
        marital_status_label: null,
        occupation: null,
        tribe: null,
        language_preference: null,
        educational_level: null,
        educational_level_label: null,
        membership_type: 'full',
        membership_type_label: 'Full',
        membership_date: '2023-04-15',
        status: 'active',
        status_label: 'Active',
        status_note: null,
        family: null,
        departments: [],
        attendance_summary: { last_4_weeks: 3, last_8_weeks: 7, last_12_weeks: 10 },
        whatsapp_opted_in: false,
        sms_opted_in: true,
        ...overrides,
    };
}

export function family(overrides: Partial<Family> = {}): Family {
    return { id: 'family-1', name: 'Mensah Family', notes: null, head_member_id: null, ...overrides };
}

/** Wraps rows in the paginated envelope the API returns. */
export function pageOf<T>(rows: T[], meta: Partial<Page<T>['meta']> = {}): Page<T> {
    return { data: rows, meta: { current_page: 1, last_page: 1, per_page: 25, total: rows.length, ...meta } };
}
