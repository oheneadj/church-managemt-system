import type { ColumnPoint } from '@/components/Reports/ColumnChart';
import type { AttendanceReport } from '@/types/reports';
import { formatDate } from '@/utils/formatDate';
import { formatDayMonth } from '@/utils/formatDayMonth';

/** "1 service", "2 services". */
function servicesText(count: number): string {
    return `${count} ${count === 1 ? 'service' : 'services'}`;
}

/** "1 first-timer", "3 first-timers". */
function firstTimersText(count: number): string {
    return `${count} ${count === 1 ? 'first-timer' : 'first-timers'}`;
}

/**
 * Turns the report into chart columns, oldest on the left (the API sends the newest first, and a reader follows time to the
 * right). A week with no service is a column of 0 that says so, never a gap.
 */
export function attendancePoints(report: AttendanceReport): ColumnPoint[] {
    const points: ColumnPoint[] =
        report.group_by === 'week'
            ? report.data.map((row) => ({
                  id: row.week,
                  label: formatDayMonth(row.week_start),
                  value: row.total,
                  title: `Week of ${formatDate(row.week_start)}`,
                  detail:
                      row.services === 0 ? 'No service recorded' : `${servicesText(row.services)} · ${firstTimersText(row.first_timers)}`,
              }))
            : report.data.map((row) => ({
                  id: row.service_id,
                  label: formatDayMonth(row.service_date),
                  value: row.total,
                  title: `${row.service_name}, ${formatDate(row.service_date)}`,
              }));

    return points.reverse();
}
