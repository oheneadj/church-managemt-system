import type { Contribution, FundType, OfferingReport, OfferingSection, Pledge } from '@/types/giving';
import type { Family, Member, MemberSummary, Page } from '@/types/members';
import type { StaffMember } from '@/types/staff';
import type {
    AttendanceRecord,
    AttendanceRecorded,
    CheckinService,
    QrCheckedIn,
    Service,
    ServiceAttendance,
    ServiceQrCode,
    ServiceSchedule,
    Visitor,
} from '@/types/services';

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
        status_changed_at: null,
        status_note: null,
        created_at: '2023-04-15T09:00:00+00:00',
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

/** A service, closed, with sensible defaults a test can override. */
export function service(overrides: Partial<Service> = {}): Service {
    return {
        id: 'service-1',
        name: 'Sunday Morning Service',
        type: 'sunday',
        type_label: 'Sunday',
        checkin_code: 'GRA7F2',
        schedule_id: null,
        service_date: '2026-10-25',
        starts_at: '09:00',
        ends_at: '11:30',
        is_open: false,
        headcount_override: null,
        notes: null,
        attendance_count: 0,
        created_at: '2026-10-20T08:00:00+00:00',
        ...overrides,
    };
}

export function attendanceRecord(overrides: Partial<AttendanceRecord> = {}): AttendanceRecord {
    return {
        id: 'record-1',
        method: 'manual',
        method_label: 'Manual',
        checked_in_at: '2026-10-25T09:12:00+00:00',
        member: { id: 'member-1', member_number: 'GRC-0001', full_name: 'Emmanuel Mensah-Boateng' },
        visitor_name: null,
        ...overrides,
    };
}

/** The attendance of a service: one page of records with the service and its headcount. */
export function serviceAttendance(
    records: AttendanceRecord[] = [attendanceRecord()],
    overrides: Partial<ServiceAttendance> = {},
): ServiceAttendance {
    return {
        ...pageOf(records),
        service: service({ is_open: true, attendance_count: records.length }),
        summary: {
            total: records.length,
            members: records.length,
            visitors: 0,
            by_method: { manual: records.length, whatsapp: 0, qr: 0, biometric: 0 },
            active_members: 10,
            attendance_rate: records.length * 10,
        },
        ...overrides,
    };
}

export function recorded(overrides: Partial<AttendanceRecorded> = {}): AttendanceRecorded {
    return { recorded: 1, already_checked_in: 0, errors: [], ...overrides };
}

export function visitor(overrides: Partial<Visitor> = {}): Visitor {
    return {
        id: 'visitor-1',
        name: 'Yaw Boateng',
        phone: '+233244999000',
        how_heard: 'A friend',
        first_visit_date: '2026-10-25',
        last_visit_date: '2026-10-25',
        visit_count: 1,
        follow_up_status: 'pending',
        follow_up_status_label: 'Pending',
        ...overrides,
    };
}

export function qrCode(overrides: Partial<ServiceQrCode> = {}): ServiceQrCode {
    return {
        token: 'a'.repeat(32),
        url: `http://grace.localhost/checkin/${'a'.repeat(32)}`,
        expires_at: '2026-10-25T09:02:00+00:00',
        expires_in: 120,
        refresh_in: 60,
        ...overrides,
    };
}

export function checkinService(overrides: Partial<CheckinService> = {}): CheckinService {
    return { name: 'Sunday Morning Service', type_label: 'Sunday', service_date: '2026-10-25', is_open: true, ...overrides };
}

export function qrCheckedIn(overrides: Partial<QrCheckedIn> = {}): QrCheckedIn {
    return { checked_in: true, already_checked_in: false, service_name: 'Sunday Morning Service', first_name: 'Emmanuel', ...overrides };
}

export function serviceSchedule(overrides: Partial<ServiceSchedule> = {}): ServiceSchedule {
    return {
        id: 'schedule-1',
        name: 'Sunday Morning Service',
        type: 'sunday',
        type_label: 'Sunday',
        weekday: 'sunday',
        weekday_label: 'Sunday',
        starts_at: '09:00',
        ends_at: '11:30',
        is_active: true,
        created_at: '2026-10-20T08:00:00+00:00',
        ...overrides,
    };
}

export function fundType(overrides: Partial<FundType> = {}): FundType {
    return { id: 'fund-1', name: 'Tithe', description: null, is_active: true, sort_order: 1, ...overrides };
}

export function contribution(overrides: Partial<Contribution> = {}): Contribution {
    return {
        id: 'gift-1',
        member: { id: 'member-1', full_name: 'Emmanuel Mensah' },
        is_anonymous: false,
        service: null,
        pledge: null,
        fund_type: { id: 'fund-1', name: 'Tithe' },
        amount: '200.00',
        amount_formatted: 'GHS 200.00',
        payment_method: 'cash',
        payment_method_label: 'Cash',
        is_voided: false,
        voided_at: null,
        void_reason: null,
        voided_by: null,
        recorded_by: 'Ama Boateng',
        notes: null,
        contributed_at: '2026-10-25T09:30:00+00:00',
        created_at: '2026-10-25T09:31:00+00:00',
        ...overrides,
    };
}

export function offeringSection(overrides: Partial<OfferingSection> = {}): OfferingSection {
    return {
        grand_total: '3150.00',
        grand_total_formatted: 'GHS 3,150.00',
        by_fund: [
            {
                fund_type: 'Tithe',
                by_method: { cash: '1200.00', momo: '850.00', card: '0.00', ussd: '0.00' },
                by_method_formatted: { cash: 'GHS 1,200.00', momo: 'GHS 850.00', card: 'GHS 0.00', ussd: 'GHS 0.00' },
                total: '2050.00',
                total_formatted: 'GHS 2,050.00',
            },
            {
                fund_type: 'Offering',
                by_method: { cash: '730.00', momo: '370.00', card: '0.00', ussd: '0.00' },
                by_method_formatted: { cash: 'GHS 730.00', momo: 'GHS 370.00', card: 'GHS 0.00', ussd: 'GHS 0.00' },
                total: '1100.00',
                total_formatted: 'GHS 1,100.00',
            },
        ],
        by_method: { cash: '1930.00', momo: '1220.00', card: '0.00', ussd: '0.00' },
        by_method_formatted: { cash: 'GHS 1,930.00', momo: 'GHS 1,220.00', card: 'GHS 0.00', ussd: 'GHS 0.00' },
        transaction_count: 42,
        anonymous_count: 7,
        ...overrides,
    };
}

export function offeringReport(overrides: Partial<OfferingReport> = {}): OfferingReport {
    return {
        service: { id: 'service-1', name: 'Sunday Morning Service', service_date: '2026-10-25' },
        totals: offeringSection(),
        no_service: {
            counted_here: true,
            ...offeringSection({
                grand_total: '0.00',
                grand_total_formatted: 'GHS 0.00',
                by_fund: [],
                by_method: { cash: '0.00', momo: '0.00', card: '0.00', ussd: '0.00' },
                by_method_formatted: { cash: 'GHS 0.00', momo: 'GHS 0.00', card: 'GHS 0.00', ussd: 'GHS 0.00' },
                transaction_count: 0,
                anonymous_count: 0,
            }),
        },
        day_grand_total: '3150.00',
        day_grand_total_formatted: 'GHS 3,150.00',
        ...overrides,
    };
}

export function pledge(overrides: Partial<Pledge> = {}): Pledge {
    return {
        id: 'pledge-1',
        member: { id: 'member-1', full_name: 'Emmanuel Mensah' },
        fund_type: { id: 'fund-1', name: 'Building Fund' },
        description: 'Building Fund 2026 pledge',
        amount_pledged: '1000.00',
        amount_pledged_formatted: 'GHS 1,000.00',
        amount_paid: '300.00',
        amount_paid_formatted: 'GHS 300.00',
        amount_outstanding: '700.00',
        amount_outstanding_formatted: 'GHS 700.00',
        progress_percent: 30,
        target_date: '2026-12-31',
        status: 'active',
        status_label: 'Active',
        created_at: '2026-10-25T09:31:00+00:00',
        ...overrides,
    };
}

export function staffMember(overrides: Partial<StaffMember> = {}): StaffMember {
    return {
        id: 'staff-1',
        name: 'Akosua Mensah',
        email: 'akosua@example.com',
        phone: '+233244123456',
        roles: ['pastor'],
        extra_permissions: [],
        is_active: true,
        last_login_at: '2026-11-05T14:30:00+00:00',
        ...overrides,
    };
}
