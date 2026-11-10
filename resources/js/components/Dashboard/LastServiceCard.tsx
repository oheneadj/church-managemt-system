import { StatCard } from '@/components/Charts/StatCard';
import type { StatDelta } from '@/components/Charts/StatDeltaLine';
import { DashboardCard } from '@/components/Dashboard/DashboardCard';
import { DASHBOARD_RECENT_SERVICES } from '@/constants/dashboard';
import { NO_FIGURE } from '@/constants/reports';
import { ROUTES } from '@/constants/routes';
import { useAttendanceReport } from '@/hooks/useReports';
import type { AttendanceServiceRow } from '@/types/reports';
import { formatCount, formatSignedCount } from '@/utils/formatCount';
import { formatDate } from '@/utils/formatDate';

/** How the last service compares with the one before it, as people at each. It is only drawn when there is a service before. */
function changeSince(last: AttendanceServiceRow, before: AttendanceServiceRow | undefined): StatDelta | undefined {
    if (before === undefined) {
        return undefined;
    }

    const difference = last.total - before.total;

    return {
        text: formatSignedCount(difference),
        direction: difference > 0 ? 'up' : difference < 0 ? 'down' : 'flat',
        against: 'vs the service before',
    };
}

/**
 * How many people came to the last service that was recorded, which service it was, how that compares with the service before it,
 * and a small trend over the recent services. For staff who may read reports.
 */
export function LastServiceCard() {
    const report = useAttendanceReport(DASHBOARD_RECENT_SERVICES);
    const services = report.data?.group_by === 'service' ? report.data.data : [];
    const last = services[0];
    const oldestFirst = [...services].reverse().map((service) => service.total);

    return (
        <DashboardCard
            label="Last service"
            to={last === undefined ? ROUTES.SERVICES : ROUTES.SERVICE(last.service_id)}
            isPending={report.isPending}
            error={report.error}
            onRetry={() => void report.refetch()}
        >
            <StatCard
                label="Last service"
                value={last === undefined ? NO_FIGURE : formatCount(last.total)}
                note={last === undefined ? 'No attendance recorded yet' : `${last.service_name}, ${formatDate(last.service_date)}`}
                delta={last === undefined ? undefined : changeSince(last, services[1])}
                trend={
                    oldestFirst.length > 1
                        ? {
                              values: oldestFirst,
                              label: `Attendance at the last ${oldestFirst.length} services, from ${oldestFirst[0]} to ${oldestFirst.at(-1)}`,
                          }
                        : undefined
                }
            />
        </DashboardCard>
    );
}
