import { useState } from 'react';
import { CHART_MAX_LABELS, CHART_MIN_SLOT_PX, CHART_VALUE_TICKS } from '@/constants/reports';
import { chartScale } from '@/utils/chartScale';
import { formatCount } from '@/utils/formatCount';

/** One thing the columns are made of, such as a fund. Its colour is a CSS colour, normally one of the chart colour tokens. */
export interface ChartSeries {
    key: string;
    name: string;
    color: string;
}

/**
 * How much of one series is in one column. `value` is only for drawing; `text` is what is shown to people. Several parts of one
 * column may share a series (and so a colour), each with its own `name` for the tooltip.
 */
export interface ColumnPart {
    seriesKey: string;
    value: number;
    text: string;
    /** What the tooltip calls this part, when that is not the series' own name. */
    name?: string;
}

/** One column: where it sits on the bottom axis, what it holds, and the words the tooltip and screen readers use. */
export interface ColumnPoint {
    id: string;
    /** The short text under the column when it is one of the labelled ones, for example "12 Oct". */
    label: string;
    /** What the column is, for the tooltip's second line and the accessible name, for example "Week of 12 Oct 2026". */
    title: string;
    /** The whole column as text, for the tooltip's first line and the accessible name, for example "124 people". */
    totalText: string;
    /** What is printed above the column when it is the highest. Leave it out where the text would be too wide to fit. */
    peakText?: string;
    /** An extra line in the tooltip, for example "2 services · 3 first-timers". */
    detail?: string;
    /** What the column is made of, stacked from the bottom in this order. */
    parts: readonly ColumnPart[];
}

interface ColumnChartProps {
    points: readonly ColumnPoint[];
    series: readonly ChartSeries[];
    /** What the numbers on the side axis are in, for example "GHS". It is printed above the axis. */
    axisCaption?: string;
    /** True while newer figures load: the chart stays where it is, dimmed, so nothing jumps. */
    refreshing?: boolean;
}

/** A column taller than this share of the plot has no room above it for the tooltip, so the tooltip sits at the top of the plot. */
const TALL_SHARE = 0.55;

/** The height of the plot, not counting the labels under it. */
const PLOT_HEIGHT_CLASS = 'h-48';

/** What a column adds up to. Used only to draw it: the words come from the figures the server formatted. */
function totalOf(point: ColumnPoint): number {
    return point.parts.reduce((sum, part) => sum + part.value, 0);
}

/**
 * Where the tooltip sits across the plot. Its anchor slides with the column: at the left end the tooltip's left edge is at the
 * plot's left edge, at the right end its right edge is at the plot's right edge, and in between it is centred on the column.
 * So it follows the pointer yet can never run out of the box, whatever its width or the screen's.
 */
function tooltipAcross(index: number, count: number): { left: string; transform: string } {
    const share = ((index + 0.5) / count) * 100;

    return { left: `${share}%`, transform: `translateX(-${share}%)` };
}

/** Above the column when there is room, otherwise at the top of the plot. */
function tooltipHeight(share: number): { bottom: string } | { top: number } {
    return share > TALL_SHARE ? { top: 0 } : { bottom: `calc(${share * 100}% + 8px)` };
}

/** The coloured blocks of one column, stacked from the bottom with a 2px gap in the surface colour between them. */
function Stack({ point, colours }: { point: ColumnPoint; colours: ReadonlyMap<string, string> }) {
    const total = totalOf(point);
    const blocks = point.parts.filter((part) => part.value > 0);

    return (
        <span className="flex h-full w-full flex-col-reverse">
            {blocks.map((part, index) => (
                <span
                    key={`${part.seriesKey}-${part.name ?? ''}`}
                    className={`block w-full ${index === blocks.length - 1 ? 'rounded-t-[4px]' : ''} ${index > 0 ? 'border-t-2 border-background' : ''}`}
                    style={{ height: `${(part.value / total) * 100}%`, backgroundColor: colours.get(part.seriesKey) }}
                />
            ))}
        </span>
    );
}

/**
 * A column chart in plain HTML. Columns are at most 24px wide with rounded tops and sit on one baseline; with several series
 * they are stacked, a 2px gap apart. The grid is a hairline, the value axis counts in round steps from 0, and only the
 * highest column prints its value. Each column is a button: hovering or focusing it shows every figure in a tooltip, and the
 * same figures are always available in the table view the page offers beside it. Two or more series always get a legend.
 * Text uses the text colours; the series colours are only on the marks.
 */
export function ColumnChart({ points, series, axisCaption, refreshing = false }: ColumnChartProps) {
    const [active, setActive] = useState<number | undefined>(undefined);
    const largest = Math.max(0, ...points.map(totalOf));
    const scale = chartScale(largest, CHART_VALUE_TICKS);
    const labelEvery = Math.ceil(points.length / CHART_MAX_LABELS);
    const highest = points.findIndex((point) => totalOf(point) === largest && largest > 0);
    const activePoint = active === undefined ? undefined : points[active];
    const colours = new Map(series.map((one) => [one.key, one.color]));
    const names = new Map(series.map((one) => [one.key, one.name]));

    return (
        <div className="flex flex-col gap-2">
            <div className="flex gap-2">
                <div aria-hidden="true" className={`relative mt-6 ${PLOT_HEIGHT_CLASS} w-11 shrink-0 text-xs text-muted-foreground`}>
                    {axisCaption !== undefined && <span className="absolute -top-6 right-0">{axisCaption}</span>}
                    {scale.ticks.map((tick) => (
                        <span
                            key={tick}
                            className="absolute right-0 translate-y-1/2 tabular-nums"
                            style={{ bottom: `${(tick / scale.max) * 100}%` }}
                        >
                            {formatCount(tick)}
                        </span>
                    ))}
                </div>
                <div className="min-w-0 flex-1 overflow-x-auto pt-6">
                    <div
                        className={`transition-opacity ${refreshing ? 'opacity-50' : ''}`}
                        style={{ minWidth: points.length * CHART_MIN_SLOT_PX }}
                    >
                        <div className={`relative ${PLOT_HEIGHT_CLASS} border-b border-viz-baseline`}>
                            {scale.ticks.slice(1).map((tick) => (
                                <div
                                    key={tick}
                                    aria-hidden="true"
                                    className="absolute inset-x-0 border-t border-viz-grid"
                                    style={{ bottom: `${(tick / scale.max) * 100}%` }}
                                />
                            ))}
                            <div className="absolute inset-0 flex items-end gap-0.5" onMouseLeave={() => setActive(undefined)}>
                                {points.map((point, index) => (
                                    <button
                                        key={point.id}
                                        type="button"
                                        aria-label={`${point.title}: ${point.totalText}`}
                                        className="flex h-full min-w-0 flex-1 items-end justify-center rounded-sm outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                                        onMouseEnter={() => setActive(index)}
                                        onFocus={() => setActive(index)}
                                        onBlur={() => setActive(undefined)}
                                    >
                                        <span
                                            aria-hidden="true"
                                            className={`relative block w-full max-w-6 ${active === index ? 'brightness-110' : ''}`}
                                            style={{ height: `${(totalOf(point) / scale.max) * 100}%` }}
                                        >
                                            <Stack point={point} colours={colours} />
                                            {index === highest && point.peakText !== undefined && (
                                                <span className="absolute -top-5 left-1/2 -translate-x-1/2 text-xs font-medium whitespace-nowrap text-foreground">
                                                    {point.peakText}
                                                </span>
                                            )}
                                        </span>
                                    </button>
                                ))}
                            </div>
                            {activePoint !== undefined && active !== undefined && (
                                <div
                                    role="tooltip"
                                    className="pointer-events-none absolute z-10 flex flex-col gap-0.5 rounded-lg border bg-popover px-2.5 py-1.5 text-xs whitespace-nowrap text-popover-foreground shadow-sm"
                                    style={{ ...tooltipAcross(active, points.length), ...tooltipHeight(totalOf(activePoint) / scale.max) }}
                                >
                                    <span className="text-sm font-semibold">{activePoint.totalText}</span>
                                    <span className="text-muted-foreground">{activePoint.title}</span>
                                    {activePoint.detail && <span className="text-muted-foreground">{activePoint.detail}</span>}
                                    {series.length > 1 &&
                                        activePoint.parts
                                            .filter((part) => part.value > 0)
                                            .map((part) => (
                                                <span key={`${part.seriesKey}-${part.name ?? ''}`} className="flex items-center gap-1.5">
                                                    <span
                                                        aria-hidden="true"
                                                        className="h-0.5 w-3"
                                                        style={{ backgroundColor: colours.get(part.seriesKey) }}
                                                    />
                                                    <span className="font-medium">{part.text}</span>
                                                    <span className="text-muted-foreground">{part.name ?? names.get(part.seriesKey)}</span>
                                                </span>
                                            ))}
                                </div>
                            )}
                        </div>
                        <div aria-hidden="true" className="flex gap-0.5 pt-1 text-xs text-muted-foreground">
                            {points.map((point, index) => (
                                <span key={point.id} className="relative h-4 min-w-0 flex-1">
                                    {(points.length - 1 - index) % labelEvery === 0 && (
                                        <span
                                            className={`absolute whitespace-nowrap ${index === points.length - 1 ? 'right-0' : 'left-1/2 -translate-x-1/2'}`}
                                        >
                                            {point.label}
                                        </span>
                                    )}
                                </span>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
            {series.length > 1 && (
                <ul aria-label="Legend" className="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                    {series.map((one) => (
                        <li key={one.key} className="flex items-center gap-1.5">
                            <span aria-hidden="true" className="size-2.5 rounded-sm" style={{ backgroundColor: one.color }} />
                            {one.name}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
