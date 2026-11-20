import { useId, useState, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from 'cn';

interface ChartFigureProps {
    title: string;
    /** One sentence on what the chart shows and over what period. */
    description?: string;
    /** The legend, for two or more series. */
    legend?: ReactNode;
    /** The same data as a table: the accessible twin of the chart, reachable with one press. */
    table: ReactNode;
    /** True while newer figures load: the chart stays on screen, dimmed, so nothing jumps. */
    refreshing?: boolean;
    children: ReactNode;
}

type View = 'chart' | 'table';

/**
 * The frame every chart sits in: a title, one sentence, a legend, and a Chart / Table switch, because every chart has a table of
 * the same figures for anyone who cannot or would rather not read the drawing. While figures refresh the previous chart stays,
 * dimmed, and the figure says it is busy.
 */
export function ChartFigure({ title, description, legend, table, refreshing = false, children }: ChartFigureProps) {
    const [view, setView] = useState<View>('chart');
    const panel = useId();

    return (
        <figure
            data-slot="chart-figure"
            aria-busy={refreshing || undefined}
            className="flex flex-col gap-4 rounded-xl border bg-card p-5 shadow-xs"
        >
            <figcaption className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 className="text-h4 font-semibold">{title}</h3>
                    {description && <p className="text-body-sm text-muted-foreground">{description}</p>}
                </div>
                <div role="group" aria-label={`${title} view`} aria-controls={panel} className="flex gap-1">
                    {(['chart', 'table'] as const).map((choice) => (
                        <Button
                            key={choice}
                            size="sm"
                            variant={view === choice ? 'secondary' : 'ghost'}
                            aria-pressed={view === choice}
                            onClick={() => setView(choice)}
                        >
                            {choice === 'chart' ? 'Chart' : 'Table'}
                        </Button>
                    ))}
                </div>
            </figcaption>
            {view === 'chart' && legend}
            <div id={panel} className={cn('transition-opacity', refreshing && 'opacity-60')}>
                {view === 'chart' ? children : table}
            </div>
        </figure>
    );
}
