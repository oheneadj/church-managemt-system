interface MeterProps {
    /** How far along, in the same units as `max`. */
    value: number;
    max: number;
    /** What is measured, for assistive technology: "Monthly goal". */
    label: string;
    /** The reading in words: "720 of 1,000 units". */
    valueText: string;
}

/**
 * A single ratio against a limit (one number against its goal), drawn as a thin bar. The fill is the first chart colour and the
 * track is a lighter step of the same colour, so the whole bar reads as one thing. It is a real meter for assistive technology.
 */
export function Meter({ value, max, label, valueText }: MeterProps) {
    const share = max <= 0 ? 0 : Math.min(Math.max(value / max, 0), 1);

    return (
        <div
            role="meter"
            aria-label={label}
            aria-valuemin={0}
            aria-valuemax={max}
            aria-valuenow={Math.min(Math.max(value, 0), max)}
            aria-valuetext={valueText}
            className="h-2 w-full overflow-hidden rounded-full"
            style={{ background: 'color-mix(in oklch, var(--viz-series-1) 18%, var(--card))' }}
        >
            <div className="h-full rounded-full" style={{ width: `${share * 100}%`, background: 'var(--viz-series-1)' }} />
        </div>
    );
}
