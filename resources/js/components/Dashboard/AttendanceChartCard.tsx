import { ChartColumnIcon } from 'lucide-react';
import { ChartFigure } from '@/components/Charts/ChartFigure';
import { ATTENDANCE_SERIES, attendancePoints } from '@/components/Reports/attendancePoints';
import { AttendanceTable } from '@/components/Reports/AttendanceTable';
import { ColumnChart } from '@/components/Reports/ColumnChart';
import { EmptyState } from '@/components/ui/EmptyState';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { Skeleton } from '@/components/ui/skeleton';
import { DASHBOARD_RECENT_SERVICES } from '@/constants/dashboard';
import { useAttendanceReport } from '@/hooks/useReports';

/**
 * Attendance at the recent services as a column chart with a table of the same figures one press away. It asks for the same
 * report as the last-service card, so it costs no extra request. It has its own loading, failure and empty states. For staff who
 * may read reports.
 */
export function AttendanceChartCard() {
    const report = useAttendanceReport(DASHBOARD_RECENT_SERVICES);
    const recorded = report.data !== undefined && report.data.data.length > 0;

    if (report.isPending) {
        return <Skeleton aria-busy="true" aria-label="Attendance chart is loading" className="h-80 w-full rounded-xl" />;
    }

    if (report.isError) {
        return <ErrorNotice message={report.error.message} onRetry={() => void report.refetch()} />;
    }

    if (!recorded || report.data === undefined) {
        return (
            <EmptyState
                icon={ChartColumnIcon}
                title="No attendance recorded yet"
                description="Once a service has attendance, how many people came to each one is charted here."
            />
        );
    }

    return (
        <ChartFigure
            title="Attendance at recent services"
            description={
                report.data.data.length === 1
                    ? 'People at the last service.'
                    : `People at each of the last ${report.data.data.length} services, oldest on the left.`
            }
            refreshing={report.isPlaceholderData}
            table={<AttendanceTable report={report.data} />}
        >
            <ColumnChart
                points={attendancePoints(report.data)}
                series={ATTENDANCE_SERIES}
                axisCaption="People"
                refreshing={report.isPlaceholderData}
            />
        </ChartFigure>
    );
}
