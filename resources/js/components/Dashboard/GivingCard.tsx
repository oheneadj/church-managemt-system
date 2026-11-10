import { FeaturedStatCard } from '@/components/Charts/StatCardVariants';
import type { StatDelta } from '@/components/Charts/StatDeltaLine';
import { DashboardCard } from '@/components/Dashboard/DashboardCard';
import { DASHBOARD_GIVING_MONTH, DASHBOARD_GIVING_TREND } from '@/constants/dashboard';
import { NO_FIGURE } from '@/constants/reports';
import { ROUTES } from '@/constants/routes';
import { useGivingReport } from '@/hooks/useReports';
import { formatMonth } from '@/utils/formatMonth';
import { formatPercent } from '@/utils/formatPercent';

/** The change the server worked out against the month before. Nothing is shown when nothing was given then. */
function changeSince(percent: number | null | undefined): StatDelta | undefined {
    return percent === null || percent === undefined
        ? undefined
        : { text: formatPercent(percent), direction: percent > 0 ? 'up' : percent < 0 ? 'down' : 'flat', against: 'vs last month' };
}

/**
 * What has been given so far this month, as the server formatted it (voided gifts are left out), set against the month before by
 * the server's own percentage, with a small trend over six months. It is the number the home page leads with, so it is the one
 * featured card. It holds totals only, so anyone who may read giving totals may see it, the pastor included (D-046). The trend is
 * drawn from the exact amounts and only ever for its shape; if it cannot be had the card shows without it.
 */
export function GivingCard() {
    const report = useGivingReport(DASHBOARD_GIVING_MONTH);
    const trend = useGivingReport(DASHBOARD_GIVING_TREND);
    const month = report.data?.data[0];
    const sixMonths = [...(trend.data?.data ?? [])].reverse().map((row) => Number(row.total));

    return (
        <DashboardCard
            label="Given this month"
            to={`${ROUTES.REPORTS}?tab=giving`}
            isPending={report.isPending}
            error={report.error}
            onRetry={() => void report.refetch()}
            placeholderClassName="h-44"
        >
            <FeaturedStatCard
                label="Given this month"
                value={month?.total_formatted ?? NO_FIGURE}
                note={month === undefined ? undefined : formatMonth(month.month_start, 'long')}
                delta={changeSince(report.data?.summary.change_percent)}
                trend={sixMonths.length > 1 ? { values: sixMonths, label: `Giving over the last ${sixMonths.length} months` } : undefined}
            />
        </DashboardCard>
    );
}
