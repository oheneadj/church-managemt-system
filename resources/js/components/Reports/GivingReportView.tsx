import { HandCoins } from 'lucide-react';
import { useState } from 'react';
import { SelectField } from '@/components/form/SelectField';
import { ColumnChart } from '@/components/Reports/ColumnChart';
import { givingPoints, givingSeries } from '@/components/Reports/givingPoints';
import { GivingTable } from '@/components/Reports/GivingTable';
import { StatTile } from '@/components/Reports/StatTile';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/EmptyState';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { Skeleton } from '@/components/ui/skeleton';
import { REPORT_MONTH_CHOICES, NO_FIGURE } from '@/constants/reports';
import { useFundTypes } from '@/hooks/useFundTypes';
import { useGivingFilters } from '@/hooks/useGivingFilters';
import { useGivingReport } from '@/hooks/useReports';
import { formatPercent } from '@/utils/formatPercent';

/** The currency the chart's side axis counts in. Every amount in the app is in Ghana cedis (D-017). */
const AXIS_CAPTION = 'GHS';

/**
 * How much was given: the choices in one row above, the period's total set against the same number of months before it, then
 * a stacked column chart by month with a block for each fund, which can be swapped for a table of the same figures. It holds
 * totals only, so a pastor, who may not see single gifts, may read it. The choices are in the address.
 */
export function GivingReportView() {
    const { filters, setFilter } = useGivingFilters();
    const report = useGivingReport(filters);
    const funds = useFundTypes();
    const [asTable, setAsTable] = useState(false);
    const gave = report.data !== undefined && report.data.data.some((row) => row.by_fund.length > 0);
    const fundOptions = (funds.data ?? []).map((fund) => ({ value: fund.id, label: fund.name }));

    return (
        <div className="flex flex-col gap-4">
            <section aria-label="Giving choices" className="grid grid-cols-1 gap-x-4 gap-y-1 sm:grid-cols-2">
                <SelectField
                    id="giving-months"
                    label="Show"
                    options={REPORT_MONTH_CHOICES}
                    value={String(filters.months)}
                    onChange={(event) => setFilter('months', event.target.value)}
                />
                <SelectField
                    id="giving-fund"
                    label="Fund"
                    options={fundOptions}
                    emptyLabel="All funds"
                    value={filters.fundId}
                    onChange={(event) => setFilter('fund', event.target.value)}
                />
            </section>

            {report.isPending && <Skeleton aria-busy="true" className="h-[320px] w-full rounded-xl" />}
            {report.isError && <ErrorNotice message={report.error.message} onRetry={() => void report.refetch()} />}

            {report.data && !gave && (
                <EmptyState
                    icon={HandCoins}
                    title="No giving recorded"
                    description="Once gifts are recorded, how much was given each month will be charted here."
                />
            )}

            {report.data && gave && (
                <>
                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <StatTile label="Given in this period" value={report.data.summary.total_formatted} />
                        <StatTile
                            label="The months before"
                            value={report.data.summary.previous_total_formatted}
                            note={`The same ${filters.months} months just before`}
                        />
                        <StatTile
                            label="Change"
                            value={
                                report.data.summary.change_percent === null ? NO_FIGURE : formatPercent(report.data.summary.change_percent)
                            }
                            note={
                                report.data.summary.change_percent === null
                                    ? 'Nothing was given in the months before'
                                    : 'Compared with the months before'
                            }
                        />
                    </div>
                    <figure className="flex flex-col gap-2 rounded-xl border p-3">
                        <figcaption className="flex flex-wrap items-center justify-between gap-2">
                            <span className="font-medium">Giving by month</span>
                            <Button type="button" variant="outline" size="sm" onClick={() => setAsTable((current) => !current)}>
                                {asTable ? 'Show as chart' : 'Show as table'}
                            </Button>
                        </figcaption>
                        {asTable ? (
                            <GivingTable report={report.data} />
                        ) : (
                            <ColumnChart
                                points={givingPoints(funds.data ?? [], report.data)}
                                series={givingSeries(funds.data ?? [], report.data)}
                                axisCaption={AXIS_CAPTION}
                                refreshing={report.isPlaceholderData}
                            />
                        )}
                    </figure>
                    <p className="text-sm text-muted-foreground">Voided gifts are left out.</p>
                </>
            )}
        </div>
    );
}
