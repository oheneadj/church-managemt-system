import { HandCoinsIcon } from 'lucide-react';
import { ChartFigure } from '@/components/Charts/ChartFigure';
import { ColumnChart } from '@/components/Reports/ColumnChart';
import { givingPoints, givingSeries } from '@/components/Reports/givingPoints';
import { GivingTable } from '@/components/Reports/GivingTable';
import { EmptyState } from '@/components/ui/EmptyState';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { Skeleton } from '@/components/ui/skeleton';
import { DASHBOARD_GIVING_TREND } from '@/constants/dashboard';
import { useFundTypes } from '@/hooks/useFundTypes';
import { useGivingReport } from '@/hooks/useReports';

/**
 * Giving by month over the last six months, a block for each fund, with a table of the same figures one press away. Amounts are
 * the texts the server formatted; the numbers behind the blocks only decide their height. Totals only, so anyone who may read
 * giving totals may see it. It has its own loading, failure and empty states.
 */
export function GivingChartCard() {
    const report = useGivingReport(DASHBOARD_GIVING_TREND);
    const funds = useFundTypes();
    const gave = report.data !== undefined && report.data.data.some((row) => row.by_fund.length > 0);

    if (report.isPending) {
        return <Skeleton aria-busy="true" aria-label="Giving chart is loading" className="h-80 w-full rounded-xl" />;
    }

    if (report.isError) {
        return <ErrorNotice message={report.error.message} onRetry={() => void report.refetch()} />;
    }

    if (!gave || report.data === undefined) {
        return (
            <EmptyState
                icon={HandCoinsIcon}
                title="No giving recorded"
                description="Once gifts are recorded, how much was given each month is charted here."
            />
        );
    }

    return (
        <ChartFigure
            title="Giving by month"
            description="The last six months, with a block for each fund. Voided gifts are left out."
            refreshing={report.isPlaceholderData}
            table={<GivingTable report={report.data} />}
        >
            <ColumnChart
                points={givingPoints(funds.data ?? [], report.data)}
                series={givingSeries(funds.data ?? [], report.data)}
                axisCaption="GHS"
                refreshing={report.isPlaceholderData}
            />
        </ChartFigure>
    );
}
