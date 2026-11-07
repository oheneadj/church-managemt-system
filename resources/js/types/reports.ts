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

/** What one fund gave in one month. Amounts are exact decimal strings in cedis, each with the text to show. */
export interface GivingFundAmount {
    fund_type_id: string;
    name: string;
    total: string;
    total_formatted: string;
}

/** One calendar month: everything given, and the funds that gave something. A month with nothing is "0.00" with no funds. */
export interface GivingMonthRow {
    month: string;
    month_start: string;
    total: string;
    total_formatted: string;
    by_fund: GivingFundAmount[];
}

/** The whole period set against the same number of months before it. `change_percent` is null when nothing was given then. */
export interface GivingSummary {
    total: string;
    total_formatted: string;
    previous_total: string;
    previous_total_formatted: string;
    change_percent: number | null;
}

/** Giving by month, newest first. Totals only: no member and no single gift is named. */
export interface GivingReport {
    data: GivingMonthRow[];
    summary: GivingSummary;
}

/** What the giving report is asked for. An empty `fundId` means every fund. */
export interface GivingFilters {
    months: number;
    fundId: string;
}
