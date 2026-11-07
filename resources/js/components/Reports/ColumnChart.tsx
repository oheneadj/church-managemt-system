import { useState } from 'react';
import { CHART_MAX_LABELS, CHART_MIN_SLOT_PX, CHART_VALUE_TICKS } from '@/constants/reports';
import { chartScale } from '@/utils/chartScale';
import { formatCount } from '@/utils/formatCount';

/** One column: where it sits on the bottom axis, its value, and the words the tooltip and screen readers use. */
export interface ColumnPoint {
    id: string;
    /** The short text under the column when it is one of the labelled ones, for example "12 Oct". */
    label: string;
    value: number;
    /** What the column is, for the tooltip's second line and the accessible name, for example "Week of 12 Oct 2026". */
    title: string;
    /** An extra line in the tooltip, for example "2 services · 3 first-timers". */
    detail?: string;
}

interface ColumnChartProps {
    points: readonly ColumnPoint[];
    /** What the values count, for the accessible names, for example "people". */
    unit: string;
    /** True while newer figures load: the chart stays where it is, dimmed, so nothing jumps. */
    refreshing?: boolean;
}

/** A column taller than this share of the plot has no room above it for the tooltip, so the tooltip sits at the top of the plot. */
const TALL_SHARE = 0.55;

/** The height of the plot, not counting the labels under it. */
const PLOT_HEIGHT_CLASS = 'h-48';

/** Where the tooltip sits across the plot: centred on its column, held inside the box at either end. */
function tooltipPlacement(index: number, count: number): string {
    const middle = (index + 0.5) / count;

    if (middle < 0.2) return 'left-0';
    if (middle > 0.8) return 'right-0';

    return '-translate-x-1/2';
}

/** Above the column when there is room, otherwise at the top of the plot. */
function tooltipHeight(share: number): { bottom: string } | { top: number } {
    return share > TALL_SHARE ? { top: 0 } : { bottom: `calc(${share * 100}% + 8px)` };
}

/**
 * A column chart of one series, in plain HTML. Columns are at most 24px wide with rounded tops and sit on one baseline. The
 * grid is a hairline, the value axis counts in round steps from 0, and only the highest column prints its value. Each column
 * is a button: hovering or focusing it shows its value in a tooltip, and the same figures are always available in the
 * table view the page offers beside it. Text uses the text colours; the series colour is only on the marks.
 */
export function ColumnChart({ points, unit, refreshing = false }: ColumnChartProps) {
    const [active, setActive] = useState<number | undefined>(undefined);
    const largest = Math.max(0, ...points.map((point) => point.value));
    const scale = chartScale(largest, CHART_VALUE_TICKS);
    const labelEvery = Math.ceil(points.length / CHART_MAX_LABELS);
    const highest = points.findIndex((point) => point.value === largest && largest > 0);
    const activePoint = active === undefined ? undefined : points[active];

    return (
        <div className="flex gap-2">
            <div aria-hidden="true" className={`relative mt-6 ${PLOT_HEIGHT_CLASS} w-9 shrink-0 text-xs text-muted-foreground`}>
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
                                    aria-label={`${point.title}: ${formatCount(point.value)} ${unit}`}
                                    className="flex h-full min-w-0 flex-1 items-end justify-center rounded-sm outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                                    onMouseEnter={() => setActive(index)}
                                    onFocus={() => setActive(index)}
                                    onBlur={() => setActive(undefined)}
                                >
                                    <span
                                        aria-hidden="true"
                                        className={`relative block w-full max-w-6 rounded-t-[4px] bg-viz-series-1 ${active === index ? 'brightness-110' : ''}`}
                                        style={{ height: `${(point.value / scale.max) * 100}%` }}
                                    >
                                        {index === highest && (
                                            <span className="absolute -top-5 left-1/2 -translate-x-1/2 text-xs font-medium whitespace-nowrap text-foreground">
                                                {formatCount(point.value)}
                                            </span>
                                        )}
                                    </span>
                                </button>
                            ))}
                        </div>
                        {activePoint !== undefined && active !== undefined && (
                            <div
                                role="tooltip"
                                className={`pointer-events-none absolute z-10 flex flex-col rounded-lg border bg-popover px-2.5 py-1.5 text-xs whitespace-nowrap text-popover-foreground shadow-sm ${tooltipPlacement(active, points.length)}`}
                                style={{
                                    left:
                                        tooltipPlacement(active, points.length) === '-translate-x-1/2'
                                            ? `${((active + 0.5) / points.length) * 100}%`
                                            : undefined,
                                    ...tooltipHeight(activePoint.value / scale.max),
                                }}
                            >
                                <span className="text-sm font-semibold">
                                    {formatCount(activePoint.value)} {unit}
                                </span>
                                <span className="text-muted-foreground">{activePoint.title}</span>
                                {activePoint.detail && <span className="text-muted-foreground">{activePoint.detail}</span>}
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
    );
}
