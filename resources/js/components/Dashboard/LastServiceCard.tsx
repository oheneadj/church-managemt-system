import { DashboardCard } from '@/components/Dashboard/DashboardCard';
import { NO_FIGURE } from '@/constants/reports';
import { ROUTES } from '@/constants/routes';
import { useAttendanceReport } from '@/hooks/useReports';
import type { AttendanceFilters } from '@/types/reports';
import { formatCount } from '@/utils/formatCount';
import { formatDate } from '@/utils/formatDate';

/**
 * Services, newest first, over the default 12 weeks. A service counts only once something is recorded for it and only up to
 * today, so the first one is the last service that really happened, never one still to come.
 */
const RECENT_SERVICES: AttendanceFilters = { weeks: 12, groupBy: 'service' };

/** How many people came to the last service that was recorded, and which service it was. For staff who may read reports. */
export function LastServiceCard() {
    const report = useAttendanceReport(RECENT_SERVICES);
    const last = report.data?.group_by === 'service' ? report.data.data[0] : undefined;

    return (
        <DashboardCard
            label="Last service"
            to={last === undefined ? ROUTES.SERVICES : ROUTES.SERVICE(last.service_id)}
            isPending={report.isPending}
            error={report.error}
            onRetry={() => void report.refetch()}
            value={last === undefined ? NO_FIGURE : formatCount(last.total)}
            note={last === undefined ? 'No attendance recorded yet' : `${last.service_name}, ${formatDate(last.service_date)}`}
        />
    );
}
