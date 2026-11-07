import { ChartColumn } from 'lucide-react';
import { useState } from 'react';
import { SelectField } from '@/components/form/SelectField';
import { AttendanceTable } from '@/components/Reports/AttendanceTable';
import { attendancePoints } from '@/components/Reports/attendancePoints';
import { ColumnChart } from '@/components/Reports/ColumnChart';
import { StatTile } from '@/components/Reports/StatTile';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/EmptyState';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { Skeleton } from '@/components/ui/skeleton';
import { REPORT_GROUPINGS } from '@/constants/enums';
import { ATTENDANCE_WEEK_CHOICES } from '@/constants/reports';
import { useAttendanceFilters } from '@/hooks/useAttendanceFilters';
import { useAttendanceReport } from '@/hooks/useReports';
import { formatCount } from '@/utils/formatCount';

/**
 * How many people came to church: the choices in one row above, the average, highest and lowest as headline numbers, then
 * a column chart that can be swapped for a table of the same figures. The choices are in the address.
 */
export function AttendanceReportView() {
    const { filters, setFilter } = useAttendanceFilters();
    const report = useAttendanceReport(filters);
    const [asTable, setAsTable] = useState(false);
    const recorded = report.data !== undefined && report.data.summary.highest > 0;

    return (
        <div className="flex flex-col gap-4">
            <section aria-label="Attendance choices" className="grid grid-cols-1 gap-x-4 gap-y-1 sm:grid-cols-2">
                <SelectField
                    id="attendance-weeks"
                    label="Show"
                    options={ATTENDANCE_WEEK_CHOICES}
                    value={String(filters.weeks)}
                    onChange={(event) => setFilter('weeks', event.target.value)}
                />
                <SelectField
                    id="attendance-group"
                    label="Group"
                    options={REPORT_GROUPINGS}
                    value={filters.groupBy}
                    onChange={(event) => setFilter('group', event.target.value)}
                />
            </section>

            {report.isPending && <Skeleton aria-busy="true" className="h-[280px] w-full rounded-xl" />}
            {report.isError && <ErrorNotice message={report.error.message} onRetry={() => void report.refetch()} />}

            {report.data && !recorded && (
                <EmptyState
                    icon={ChartColumn}
                    title="No attendance recorded"
                    description="Once people are checked in to a service, how many came will be charted here."
                />
            )}

            {report.data && recorded && (
                <>
                    <div className="grid grid-cols-3 gap-2">
                        <StatTile label="Average" value={formatCount(report.data.summary.average)} />
                        <StatTile label="Highest" value={formatCount(report.data.summary.highest)} />
                        <StatTile label="Lowest" value={formatCount(report.data.summary.lowest)} />
                    </div>
                    <figure className="flex flex-col gap-2 rounded-xl border p-3">
                        <figcaption className="flex flex-wrap items-center justify-between gap-2">
                            <span className="font-medium">People at services</span>
                            <Button type="button" variant="outline" size="sm" onClick={() => setAsTable((current) => !current)}>
                                {asTable ? 'Show as chart' : 'Show as table'}
                            </Button>
                        </figcaption>
                        {asTable ? (
                            <AttendanceTable report={report.data} />
                        ) : (
                            <ColumnChart points={attendancePoints(report.data)} unit="people" refreshing={report.isPlaceholderData} />
                        )}
                    </figure>
                </>
            )}
        </div>
    );
}
