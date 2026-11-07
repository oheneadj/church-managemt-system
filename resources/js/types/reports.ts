/**
 * Shapes of the report endpoints (docs/08-api.md, "Reports").
 */
import type { ReportGrouping } from '@/constants/enums';

/** The figures over the weeks (or services) in which something was recorded. */
export interface AttendanceSummary {
    average: number;
    highest: number;
    lowest: number;
}

/** One ISO week, Monday to Sunday. A week with no service has a total of 0. */
export interface AttendanceWeekRow {
    week: string;
    week_start: string;
    total: number;
    first_timers: number;
    services: number;
}

/** One service. */
export interface AttendanceServiceRow {
    service_id: string;
    service_date: string;
    service_name: string;
    total: number;
}

/** The attendance trend, by week or by service, newest first. */
export type AttendanceReport =
    | { group_by: 'week'; data: AttendanceWeekRow[]; summary: AttendanceSummary }
    | { group_by: 'service'; data: AttendanceServiceRow[]; summary: AttendanceSummary };

/** What the attendance report is asked for. */
export interface AttendanceFilters {
    weeks: number;
    groupBy: ReportGrouping;
}
