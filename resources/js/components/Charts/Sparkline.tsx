interface SparklineProps {
    /** The values in time order; the last one is the current period. At least two. */
    values: readonly number[];
    /** What the trend is, in words, for assistive technology: it is the text alternative of the whole drawing. */
    label: string;
    /** True on a deep filled card: the line is then drawn in the card's own light text colour, earlier periods fainter, so it reads on the fill. */
    inverse?: boolean;
}

const WIDTH = 96;
const HEIGHT = 28;
const PAD = 4;

/**
 * A small trend line beside a number (docs/design-system/foundations.md, charts). Earlier periods are drawn in the quiet
 * de-emphasis colour and the current period in the first chart colour, ending in a dot with a ring of the card's own colour so it
 * stays clear of the line. It carries no numbers: the stat it sits in says them.
 */
export function Sparkline({ values, label, inverse = false }: SparklineProps) {
    const low = Math.min(...values);
    const span = Math.max(Math.max(...values) - low, 1);
    const step = (WIDTH - PAD * 2) / Math.max(values.length - 1, 1);
    const points = values.map((value, index) => [PAD + index * step, HEIGHT - PAD - ((value - low) / span) * (HEIGHT - PAD * 2)] as const);
    const earlier = points
        .slice(0, -1)
        .map(([x, y]) => `${x},${y}`)
        .join(' ');
    const [from, to] = [points.at(-2), points.at(-1)];

    return (
        <svg role="img" aria-label={label} width={WIDTH} height={HEIGHT} viewBox={`0 0 ${WIDTH} ${HEIGHT}`} className="shrink-0">
            {points.length > 2 && (
                <polyline
                    points={earlier}
                    fill="none"
                    stroke={inverse ? 'currentColor' : 'var(--viz-other)'}
                    opacity={inverse ? 0.5 : undefined}
                    strokeWidth={2}
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
            )}
            {from && to && (
                <line
                    x1={from[0]}
                    y1={from[1]}
                    x2={to[0]}
                    y2={to[1]}
                    stroke={inverse ? 'currentColor' : 'var(--viz-series-1)'}
                    strokeWidth={2}
                    strokeLinecap="round"
                />
            )}
            {to && (
                <circle
                    cx={to[0]}
                    cy={to[1]}
                    r={4}
                    fill={inverse ? 'currentColor' : 'var(--viz-series-1)'}
                    stroke={inverse ? 'var(--primary)' : 'var(--card)'}
                    strokeWidth={2}
                />
            )}
        </svg>
    );
}
