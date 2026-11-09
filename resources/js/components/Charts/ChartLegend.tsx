export interface LegendItem {
    key: string;
    name: string;
    /** A CSS colour, normally a chart colour token. */
    color: string;
}

interface ChartLegendProps {
    items: readonly LegendItem[];
    /** A short line for a line chart, a small square for bars and segments. The key mirrors the mark. */
    shape: 'line' | 'swatch';
}

/**
 * The legend: every series named once, in the order they are drawn, each with a key that mirrors its mark. It is shown for two or
 * more series (one series is named by the chart's title) and carries no numbers. The names are plain text; the colour is only the key.
 */
export function ChartLegend({ items, shape }: ChartLegendProps) {
    return (
        <ul aria-label="Legend" className="flex flex-wrap gap-x-4 gap-y-1 text-body-sm text-muted-foreground">
            {items.map((item) => (
                <li key={item.key} className="flex items-center gap-1.5">
                    <span
                        aria-hidden="true"
                        className={shape === 'line' ? 'h-0.5 w-4 rounded-full' : 'size-2.5 rounded-sm'}
                        style={{ background: item.color }}
                    />
                    {item.name}
                </li>
            ))}
        </ul>
    );
}
