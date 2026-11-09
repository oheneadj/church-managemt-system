import { LayersIcon, TargetIcon } from 'lucide-react';
import { ChartFigure } from '@/components/Charts/ChartFigure';
import { ChartLegend } from '@/components/Charts/ChartLegend';
import { LineChart, type LinePoint, type LineSeries } from '@/components/Charts/LineChart';
import { SegmentedBar, type SegmentPart } from '@/components/Charts/SegmentedBar';
import { StatCard } from '@/components/Charts/StatCard';
import { FeaturedStatCard, IconStatCard, ProgressStatCard } from '@/components/Charts/StatCardVariants';
import { ShowcaseSection } from '@/components/DesignSystem/ShowcaseSection';
import { ColumnChart, type ChartSeries, type ColumnPoint } from '@/components/Reports/ColumnChart';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatCount } from '@/utils/formatCount';

const WEEKLY = [124, 131, 118, 142, 156, 149, 171, 183];
const MONTHS = ['May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct'];
const MONTH_TITLES = ['May 2026', 'Jun 2026', 'Jul 2026', 'Aug 2026', 'Sep 2026', 'Oct 2026'];

const COLUMN_SERIES: readonly ChartSeries[] = [{ key: 'visits', name: 'Visits', color: 'var(--viz-series-1)' }];
const COLUMNS: ColumnPoint[] = WEEKLY.map((total, index) => ({
    id: `w${index + 1}`,
    label: `W${index + 1}`,
    title: `Week ${index + 1}`,
    totalText: `${formatCount(total)} visits`,
    peakText: formatCount(total),
    parts: [{ seriesKey: 'visits', value: total, text: formatCount(total) }],
}));

const LINE_POINTS: LinePoint[] = MONTHS.map((label, index) => ({ id: label, label, title: MONTH_TITLES[index] ?? label }));
const LINE_SERIES: LineSeries[] = [
    { key: 'a', name: 'Series A', color: 'var(--viz-series-1)', values: [820, 910, 880, 1040, 1120, 1260] },
    { key: 'b', name: 'Series B', color: 'var(--viz-series-2)', values: [610, 640, 720, 690, 760, 810] },
];

const SEGMENTS: SegmentPart[] = [
    { key: 'alpha', name: 'Alpha', value: 4200, amountText: '4,200 units', color: 'var(--viz-series-1)', ink: 'var(--viz-ink-1)' },
    { key: 'bravo', name: 'Bravo', value: 2600, amountText: '2,600 units', color: 'var(--viz-series-2)', ink: 'var(--viz-ink-2)' },
    { key: 'charlie', name: 'Charlie', value: 1500, amountText: '1,500 units', color: 'var(--viz-series-3)', ink: 'var(--viz-ink-3)' },
    { key: 'delta', name: 'Delta', value: 500, amountText: '500 units', color: 'var(--viz-series-4)', ink: 'var(--viz-ink-4)' },
];

const TREND_UP = [18, 20, 19, 23, 24, 27, 26, 29, 31, 30, 34, 38];
const COLUMNS_FALLING = [560, 540, 520, 505, 480, 455, 436];

/** The same figures as a table: the twin every chart has. */
function FiguresTable({ caption, head, rows }: { caption: string; head: string[]; rows: string[][] }) {
    return (
        <Table>
            <TableCaption>{caption}</TableCaption>
            <TableHeader>
                <TableRow className="hover:bg-transparent">
                    {head.map((name, column) => (
                        <TableHead key={name} numeric={column > 0}>
                            {name}
                        </TableHead>
                    ))}
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((row) => (
                    <TableRow key={row[0]}>
                        {row.map((cell, column) => (
                            <TableCell key={column} numeric={column > 0}>
                                {cell}
                            </TableCell>
                        ))}
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

/**
 * Stats and charts: four stat card designs (trend, icon with a mini chart, progress with a meter, featured with the hero figure), a column chart, a two-series line chart and a segmented bar. Each chart
 * has a legend where it needs one, a hover and keyboard readout, and a table of the same figures one press away.
 */
export function ChartsSection() {
    const total = SEGMENTS.reduce((sum, part) => sum + part.value, 0);

    return (
        <ShowcaseSection
            id="charts"
            title="Stats and charts"
            description="One hue for one series, a validated set of colours for several, thin marks with a gap between them, and a table behind every chart. Hover, or use the arrow keys on the line chart."
        >
            <div className="grid gap-6 sm:grid-cols-2">
                <StatCard
                    label="Total items"
                    value="1,284"
                    delta={{ text: '+12.4%', direction: 'up', against: 'vs last month' }}
                    trend={{ values: TREND_UP, label: 'Rising over the last 12 periods, from 18 to 38' }}
                />
                <IconStatCard
                    icon={LayersIcon}
                    label="Weekly visits"
                    value="436"
                    delta={{ text: '-4.2%', direction: 'down', against: 'vs last week' }}
                    columns={{ values: COLUMNS_FALLING, label: 'Visits in each of the last 7 weeks, falling from 560 to 436' }}
                />
                <ProgressStatCard
                    icon={TargetIcon}
                    label="Monthly goal"
                    value="72%"
                    progress={{ value: 720, max: 1000, label: 'Monthly goal progress', text: '720 of 1,000 units' }}
                />
                <FeaturedStatCard
                    label="Total this month"
                    value="24,310"
                    delta={{ text: '+8.1%', direction: 'up', against: 'vs last month' }}
                    trend={{ values: TREND_UP, label: 'Monthly total rising over the last 12 periods' }}
                />
            </div>
            <ChartFigure
                title="Weekly visits"
                description="Visits in each of the last 8 weeks."
                table={
                    <FiguresTable
                        caption="Weekly visits"
                        head={['Week', 'Visits']}
                        rows={WEEKLY.map((total, index) => [`Week ${index + 1}`, formatCount(total)])}
                    />
                }
            >
                <ColumnChart points={COLUMNS} series={COLUMN_SERIES} axisCaption="Visits" />
            </ChartFigure>
            <ChartFigure
                title="Two series over six months"
                description="Series A and Series B, May to October."
                legend={<ChartLegend shape="line" items={LINE_SERIES} />}
                table={
                    <FiguresTable
                        caption="Two series over six months"
                        head={['Month', 'Series A', 'Series B']}
                        rows={MONTHS.map((month, index) => [
                            month,
                            formatCount(LINE_SERIES[0]?.values[index] ?? 0),
                            formatCount(LINE_SERIES[1]?.values[index] ?? 0),
                        ])}
                    />
                }
            >
                <LineChart name="Two series over six months" points={LINE_POINTS} series={LINE_SERIES} formatValue={formatCount} />
            </ChartFigure>
            <ChartFigure
                title="One whole in four parts"
                description="How 8,800 units split across four parts."
                table={
                    <FiguresTable
                        caption="One whole in four parts"
                        head={['Part', 'Units', 'Share']}
                        rows={SEGMENTS.map((part) => [part.name, formatCount(part.value), `${Math.round((part.value / total) * 100)}%`])}
                    />
                }
            >
                <SegmentedBar name="One whole in four parts" parts={SEGMENTS} />
            </ChartFigure>
        </ShowcaseSection>
    );
}
