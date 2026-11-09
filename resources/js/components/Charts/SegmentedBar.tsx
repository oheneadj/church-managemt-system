import { useState } from 'react';
import { ChartLegend } from '@/components/Charts/ChartLegend';

export interface SegmentPart {
    key: string;
    name: string;
    /** The size of the part, used to draw it and to work out its share. A part of 0 draws nothing. */
    value: number;
    /** The amount as people read it ("GHS 4,200"), already formatted. */
    amountText: string;
    /** A CSS colour, normally a chart colour token. */
    color: string;
    /** The colour of text printed on this part, chosen for contrast with `color` in both themes (the `--viz-ink-*` tokens). */
    ink: string;
}

interface SegmentedBarProps {
    /** What the bar is, for assistive technology: "Giving by fund". */
    name: string;
    parts: readonly SegmentPart[];
}

/** A part narrower than this share of the bar has no room for its percentage, so it prints nothing; the readout and the table carry it. */
const LABEL_MIN_SHARE = 0.12;

/**
 * One whole split into a few parts (docs/design-system/foundations.md, charts): a tall bar, parts touching with a 2px gap of the
 * card's colour between them, 4px corners, each part's share printed on it only where it fits, and a legend of names. Hovering or
 * focusing a part names it, its amount and its share. Six parts at most; fold a long tail into "Other" before drawing.
 */
export function SegmentedBar({ name, parts }: SegmentedBarProps) {
    const [active, setActive] = useState<string | null>(null);
    const drawn = parts.filter((part) => part.value > 0);
    const total = drawn.reduce((sum, part) => sum + part.value, 0);
    const share = (part: SegmentPart) => (total === 0 ? 0 : part.value / total);
    const percent = (part: SegmentPart) => `${Math.round(share(part) * 100)}%`;
    const chosen = parts.find((part) => part.key === active);

    return (
        <div className="flex flex-col gap-3">
            <div role="group" aria-label={name} className="flex h-7 gap-0.5" onPointerLeave={() => setActive(null)}>
                {drawn.map((part) => (
                    <button
                        key={part.key}
                        type="button"
                        aria-label={`${part.name}: ${part.amountText}, ${percent(part)}`}
                        aria-pressed={active === part.key}
                        onClick={() => setActive((now) => (now === part.key ? null : part.key))}
                        onPointerEnter={() => setActive(part.key)}
                        onFocus={() => setActive(part.key)}
                        onBlur={() => setActive(null)}
                        className="flex min-w-0 items-center overflow-hidden rounded-sm px-2 text-caption font-medium outline-none focus-visible:ring-3 focus-visible:ring-ring"
                        style={{
                            flex: part.value,
                            background: part.color,
                            color: part.ink,
                            opacity: active === null || active === part.key ? 1 : 0.55,
                        }}
                    >
                        {share(part) >= LABEL_MIN_SHARE ? percent(part) : null}
                    </button>
                ))}
            </div>
            <p aria-live="polite" className="min-h-5 text-body-sm text-muted-foreground">
                {chosen ? (
                    <>
                        <span className="font-semibold text-foreground">{chosen.amountText}</span> {chosen.name}, {percent(chosen)} of the
                        whole
                    </>
                ) : (
                    'Hover or focus a part to read it.'
                )}
            </p>
            <ChartLegend shape="swatch" items={parts} />
        </div>
    );
}
