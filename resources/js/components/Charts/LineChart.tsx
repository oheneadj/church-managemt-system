import { useState, type KeyboardEvent, type PointerEvent } from 'react';
import { useElementWidth } from '@/components/Charts/useElementWidth';
import { CHART_MAX_LABELS, CHART_VALUE_TICKS } from '@/constants/reports';
import { chartScale } from '@/utils/chartScale';

export interface LineSeries {
    key: string;
    name: string;
    /** A CSS colour, normally a chart colour token. */
    color: string;
    /** One value for each point, in the same order. Used only to draw: the words come from `formatValue`. */
    values: readonly number[];
}

export interface LinePoint {
    id: string;
    /** The short text under the line when it is one of the labelled points: "Oct". */
    label: string;
    /** What the point is, for the readout and the text alternative: "October 2026". */
    title: string;
}

interface LineChartProps {
    /** What the chart is, for assistive technology: "Giving by month, two funds". */
    name: string;
    points: readonly LinePoint[];
    series: readonly LineSeries[];
    /** A value as people read it ("GHS 12,400"). Only ever shows text; the chart does no money arithmetic of its own. */
    formatValue: (value: number) => string;
}

const HEIGHT = 220;
const MARGIN = { top: 16, right: 16, bottom: 28, left: 52 };
const FALLBACK_WIDTH = 600;

/**
 * A line chart (docs/design-system/foundations.md, charts): thin 2px lines, a hairline grid, an axis that starts at 0 and counts in
 * round steps, an end marker with a ring in the card's own colour, and a hover and keyboard readout that lists every series at the
 * chosen point. The readout only repeats what the table twin shows; it never gates a value. Arrow keys move through the points.
 */
export function LineChart({ name, points, series, formatValue }: LineChartProps) {
    const [ref, width] = useElementWidth<HTMLDivElement>(FALLBACK_WIDTH);
    const [active, setActive] = useState<number | null>(null);
    const plotWidth = Math.max(width - MARGIN.left - MARGIN.right, 1);
    const plotHeight = HEIGHT - MARGIN.top - MARGIN.bottom;
    const scale = chartScale(Math.max(...series.flatMap((one) => one.values)), CHART_VALUE_TICKS);
    const last = points.length - 1;
    const xAt = (index: number) => MARGIN.left + (last === 0 ? plotWidth / 2 : (index / last) * plotWidth);
    const yAt = (value: number) => MARGIN.top + plotHeight - (value / scale.max) * plotHeight;
    const labelEvery = Math.max(Math.ceil(points.length / CHART_MAX_LABELS), 1);

    const move = (event: PointerEvent<SVGRectElement>) => {
        const box = event.currentTarget.getBoundingClientRect();
        const along = ((event.clientX - box.left) / Math.max(box.width, 1)) * plotWidth;

        setActive(last === 0 ? 0 : Math.min(Math.max(Math.round((along / plotWidth) * last), 0), last));
    };

    const key = (event: KeyboardEvent<HTMLDivElement>) => {
        const target = { ArrowLeft: Math.max((active ?? 1) - 1, 0), ArrowRight: Math.min((active ?? -1) + 1, last), Home: 0, End: last }[
            event.key
        ];

        if (target !== undefined) {
            event.preventDefault();
            setActive(target);
        }
    };

    const point = active === null ? null : points[active];
    // What a screen reader says for the chosen point: its title, then each series with its value.
    const spoken = `${points[active ?? last]?.title ?? ''}: ${series.map((one) => `${one.name} ${formatValue(one.values[active ?? last] ?? 0)}`).join(', ')}`;
    const flip = active !== null && xAt(active) > width / 2;

    return (
        <div
            ref={ref}
            role="slider"
            aria-label={`${name}. Use the left and right arrow keys to read each point.`}
            aria-orientation="horizontal"
            aria-valuemin={0}
            aria-valuemax={last}
            aria-valuenow={active ?? last}
            aria-valuetext={spoken}
            tabIndex={0}
            onKeyDown={key}
            onFocus={() => setActive((now) => now ?? last)}
            onBlur={() => setActive(null)}
            onPointerLeave={() => setActive(null)}
            className="relative rounded-lg outline-none focus-visible:ring-3 focus-visible:ring-ring"
        >
            <svg
                width={width}
                height={HEIGHT}
                role="img"
                aria-label={`${name}, ${points.length} points`}
                className="block text-muted-foreground"
            >
                {scale.ticks.map((tick) => (
                    <g key={tick}>
                        <line
                            x1={MARGIN.left}
                            x2={MARGIN.left + plotWidth}
                            y1={yAt(tick)}
                            y2={yAt(tick)}
                            stroke={tick === 0 ? 'var(--viz-baseline)' : 'var(--viz-grid)'}
                            strokeWidth={1}
                        />
                        <text
                            x={MARGIN.left - 8}
                            y={yAt(tick)}
                            textAnchor="end"
                            dominantBaseline="central"
                            className="fill-current text-caption"
                        >
                            {formatValue(tick)}
                        </text>
                    </g>
                ))}
                {points.map((one, index) =>
                    index % labelEvery === 0 || index === last ? (
                        <text key={one.id} x={xAt(index)} y={HEIGHT - 8} textAnchor="middle" className="fill-current text-caption">
                            {one.label}
                        </text>
                    ) : null,
                )}
                {active !== null && (
                    <line
                        x1={xAt(active)}
                        x2={xAt(active)}
                        y1={MARGIN.top}
                        y2={MARGIN.top + plotHeight}
                        stroke="var(--viz-baseline)"
                        strokeWidth={1}
                    />
                )}
                {series.map((one) => {
                    const path = one.values.map((value, index) => `${index === 0 ? 'M' : 'L'}${xAt(index)},${yAt(value)}`).join(' ');
                    const area = series.length === 1 ? `${path} L${xAt(last)},${yAt(0)} L${xAt(0)},${yAt(0)} Z` : null;

                    return (
                        <g key={one.key}>
                            {area && <path d={area} fill={one.color} opacity={0.1} />}
                            <path d={path} fill="none" stroke={one.color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
                            {[active ?? last].map((index) => (
                                <circle
                                    key={index}
                                    cx={xAt(index)}
                                    cy={yAt(one.values[index] ?? 0)}
                                    r={4}
                                    fill={one.color}
                                    stroke="var(--card)"
                                    strokeWidth={2}
                                />
                            ))}
                        </g>
                    );
                })}
                <rect x={MARGIN.left} y={MARGIN.top} width={plotWidth} height={plotHeight} fill="transparent" onPointerMove={move} />
            </svg>
            <div
                aria-live="polite"
                className="pointer-events-none absolute top-2 z-10 min-w-40 rounded-lg border bg-popover p-3 text-popover-foreground shadow-md"
                style={{
                    left: active === null ? 0 : xAt(active),
                    transform: flip ? 'translateX(calc(-100% - 12px))' : 'translateX(12px)',
                    visibility: point ? 'visible' : 'hidden',
                }}
            >
                {point && (
                    <>
                        <p className="mb-1 text-body-sm text-muted-foreground">{point.title}</p>
                        <ul className="flex flex-col gap-1">
                            {series.map((one) => (
                                <li key={one.key} className="flex items-center gap-2 text-body-sm">
                                    <span aria-hidden="true" className="h-0.5 w-3 rounded-full" style={{ background: one.color }} />
                                    <span className="font-semibold">{formatValue(one.values[active ?? 0] ?? 0)}</span>
                                    <span className="text-muted-foreground">{one.name}</span>
                                </li>
                            ))}
                        </ul>
                    </>
                )}
            </div>
        </div>
    );
}
