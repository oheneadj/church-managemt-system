import type { CheckInMethod, ServiceType } from '@/constants/enums';
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
