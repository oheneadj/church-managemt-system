import type { CheckInMethod, ServiceType, Weekday } from '@/constants/enums';
import type { Page } from '@/types/members';

/**
 * Shapes of the service and attendance endpoints (docs/08-api.md, docs/openapi.json).
 */

export interface Service {
    id: string;
    name: string;
    type: ServiceType;
    type_label: string;
    checkin_code: string | null;
    /** The weekly schedule that made this service, or null for one made by hand. */
    schedule_id: string | null;
    service_date: string;
    starts_at: string | null;
    ends_at: string | null;
    is_open: boolean;
    headcount_override: number | null;
    notes: string | null;
    attendance_count?: number;
    created_at: string | null;
}

export interface ServiceInput {
    name: string;
    type: ServiceType;
    service_date: string;
    starts_at?: string;
    ends_at?: string;
    notes?: string;
}

export interface ServiceFilters {
    type: ServiceType | '';
    page: number;
}

export interface AttendanceRecord {
    id: string;
    method: CheckInMethod;
    method_label: string;
    checked_in_at: string;
    member: { id: string; member_number: string; full_name: string } | null;
    visitor_name: string | null;
}

export interface AttendanceSummary {
    total: number;
    members: number;
    visitors: number;
    by_method: Record<CheckInMethod, number>;
    active_members: number;
    attendance_rate: number;
}

/** One page of the people checked in to a service, with the service and its headcount beside the page. */
export interface ServiceAttendance extends Page<AttendanceRecord> {
    service: Service;
    summary: AttendanceSummary;
}

export interface AttendanceRecorded {
    recorded: number;
    already_checked_in: number;
    errors: { member_id: string; message: string }[];
}

export interface VisitorInput {
    name: string;
    phone?: string;
    how_heard?: string;
}

/** The code to draw as a QR code at the venue. */
export interface ServiceQrCode {
    token: string;
    url: string;
    expires_at: string;
    expires_in: number;
    refresh_in: number;
}

/** What a member who scanned the QR code is told about the service. */
export interface CheckinService {
    name: string;
    type_label: string;
    service_date: string;
    is_open: boolean;
}

export interface QrCheckedIn {
    checked_in: boolean;
    already_checked_in: boolean;
    service_name: string;
    first_name: string;
}

export interface Visitor {
    id: string;
    name: string;
    phone: string | null;
    how_heard: string | null;
    first_visit_date: string;
    last_visit_date: string | null;
    visit_count: number;
    follow_up_status: string;
    follow_up_status_label: string;
}

/** A weekly pattern that makes services ahead of time (docs/08, Service Schedules). Times are `H:i`. */
export interface ServiceSchedule {
    id: string;
    name: string;
    type: ServiceType;
    type_label: string;
    weekday: Weekday;
    weekday_label: string;
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
    created_at: string | null;
}

export interface ServiceScheduleInput {
    name: string;
    type: ServiceType;
    weekday: Weekday;
    starts_at: string | null;
    ends_at: string | null;
    is_active?: boolean;
}
