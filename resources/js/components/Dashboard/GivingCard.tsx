import { DashboardCard } from '@/components/Dashboard/DashboardCard';
import { NO_FIGURE } from '@/constants/reports';
import { ROUTES } from '@/constants/routes';
import { useGivingReport } from '@/hooks/useReports';
import type { GivingFilters } from '@/types/reports';
import { formatMonth } from '@/utils/formatMonth';

/** This calendar month only, for every fund. */
const THIS_MONTH: GivingFilters = { months: 1, fundId: '' };

/**
 * What has been given so far this month, as the server formatted it (voided gifts are left out). It holds a total only, so
 * anyone who may read giving totals may see it, the pastor included (D-046). It leads to the giving report.
 */
export function GivingCard() {
    const report = useGivingReport(THIS_MONTH);
    const month = report.data?.data[0];

    return (
        <DashboardCard
            label="Given this month"
            to={`${ROUTES.REPORTS}?tab=giving`}
            isPending={report.isPending}
            error={report.error}
            onRetry={() => void report.refetch()}
            value={month?.total_formatted ?? NO_FIGURE}
            note={month === undefined ? undefined : formatMonth(month.month_start, 'long')}
        />
    );
}
