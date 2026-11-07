import type { ChartSeries, ColumnPoint } from '@/components/Reports/ColumnChart';
import type { MemberGrowthReport } from '@/types/reports';
import { formatCount, formatSignedCount } from '@/utils/formatCount';
import { formatMonth } from '@/utils/formatMonth';

/** New members are drawn above the baseline and lost ones below it: blue and red, the diverging pair, one colour for each side. */
export const MEMBER_GROWTH_SERIES: readonly ChartSeries[] = [
    { key: 'new', name: 'New', color: 'var(--viz-series-1)' },
    { key: 'lost', name: 'Lost', color: 'var(--viz-negative)' },
];

/**
 * Turns the report into chart columns, oldest on the left. Each column has the people who became active above the baseline and
 * the people who left below it; the column's own words are the net change, and the tooltip adds how many members were
 * active at the end of the month.
 */
export function memberGrowthPoints(report: MemberGrowthReport): ColumnPoint[] {
    return report.data
        .map((row): ColumnPoint => ({
            id: row.month,
            label: formatMonth(row.month_start, 'short'),
            title: formatMonth(row.month_start, 'long'),
            totalText: `Net ${formatSignedCount(row.net)}`,
            detail: `${formatCount(row.active_total)} active at the end of the month`,
            parts: [
                { seriesKey: 'new', value: row.new, text: formatCount(row.new) },
                { seriesKey: 'lost', value: -row.lost, text: formatCount(row.lost) },
            ],
        }))
        .reverse();
}
