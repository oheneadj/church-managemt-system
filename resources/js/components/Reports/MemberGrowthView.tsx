import { UserRoundCheck } from 'lucide-react';
import { useState } from 'react';
import { SelectField } from '@/components/form/SelectField';
import { ColumnChart } from '@/components/Reports/ColumnChart';
import { MEMBER_GROWTH_SERIES, memberGrowthPoints } from '@/components/Reports/memberGrowthPoints';
import { MemberGrowthTable } from '@/components/Reports/MemberGrowthTable';
import { StatTile } from '@/components/Reports/StatTile';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/EmptyState';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { Skeleton } from '@/components/ui/skeleton';
import { REPORT_MONTH_CHOICES } from '@/constants/reports';
import { useMemberGrowthFilters } from '@/hooks/useMemberGrowthFilters';
import { useMemberGrowthReport } from '@/hooks/useReports';
import { formatCount, formatSignedCount } from '@/utils/formatCount';

/**
 * How the church is growing (D-063): the number of active members now, and over the period how many became active, how many
 * left and the net change, then a chart with the new above a baseline and the lost below it, which can be swapped for a
 * table of the same figures. It holds counts only, so no member is named. The choice of months is in the address.
 */
export function MemberGrowthView() {
    const { months, setMonths } = useMemberGrowthFilters();
    const report = useMemberGrowthReport(months);
    const [asTable, setAsTable] = useState(false);
    const changed = report.data !== undefined && (report.data.summary.new > 0 || report.data.summary.lost > 0);
    const activeNow = report.data?.data[0]?.active_total;

    return (
        <div className="flex flex-col gap-4">
            <section aria-label="Member growth choices" className="grid grid-cols-1 gap-x-4 gap-y-1 sm:grid-cols-2">
                <SelectField
                    id="members-months"
                    label="Show"
                    options={REPORT_MONTH_CHOICES}
                    value={String(months)}
                    onChange={(event) => setMonths(event.target.value)}
                />
            </section>

            {report.isPending && <Skeleton aria-busy="true" className="h-[300px] w-full rounded-xl" />}
            {report.isError && <ErrorNotice message={report.error.message} onRetry={() => void report.refetch()} />}

            {report.data && (
                <>
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <StatTile label="Active members" value={formatCount(activeNow ?? 0)} note="Today" />
                        <StatTile label="New" value={formatCount(report.data.summary.new)} note="Became active" />
                        <StatTile label="Lost" value={formatCount(report.data.summary.lost)} note="Left active membership" />
                        <StatTile label="Net" value={formatSignedCount(report.data.summary.net)} note="New minus lost" />
                    </div>

                    {!changed && (
                        <EmptyState
                            icon={UserRoundCheck}
                            title="No change in membership"
                            description="Nobody became an active member or left active membership in this period."
                        />
                    )}

                    {changed && (
                        <figure className="flex flex-col gap-2 rounded-xl border p-3">
                            <figcaption className="flex flex-wrap items-center justify-between gap-2">
                                <span className="font-medium">New and lost members by month</span>
                                <Button type="button" variant="outline" size="sm" onClick={() => setAsTable((current) => !current)}>
                                    {asTable ? 'Show as chart' : 'Show as table'}
                                </Button>
                            </figcaption>
                            {asTable ? (
                                <MemberGrowthTable report={report.data} />
                            ) : (
                                <ColumnChart
                                    points={memberGrowthPoints(report.data)}
                                    series={MEMBER_GROWTH_SERIES}
                                    axisCaption="People"
                                    refreshing={report.isPlaceholderData}
                                />
                            )}
                        </figure>
                    )}
                </>
            )}
        </div>
    );
}
