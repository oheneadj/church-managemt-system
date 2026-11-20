import type { LucideIcon } from 'lucide-react';
import { Meter } from '@/components/Charts/Meter';
import { MiniColumns } from '@/components/Charts/MiniColumns';
import { Sparkline } from '@/components/Charts/Sparkline';
import { StatDeltaLine, type StatDelta } from '@/components/Charts/StatDeltaLine';

/** The small tinted square that holds a card's icon. The icon is decoration: the label beside it says what the card is. */
function IconBadge({ icon: Icon }: { icon: LucideIcon }) {
    return (
        <span aria-hidden="true" className="grid size-10 shrink-0 place-items-center rounded-lg bg-secondary text-primary">
            <Icon className="size-5" />
        </span>
    );
}

interface IconStatCardProps {
    icon: LucideIcon;
    label: string;
    value: string;
    /** A small line under the number. */
    note?: string;
    delta?: StatDelta;
    /** The values for the mini column chart, in time order. */
    columns?: { values: readonly number[]; label: string };
}

/**
 * The icon card: a tinted icon beside the label, the number, a change, and a small column chart on the right. The icon helps
 * a person scan a row of cards; it never carries the meaning on its own.
 */
export function IconStatCard({ icon, label, value, note, delta, columns }: IconStatCardProps) {
    return (
        <div data-slot="stat-card" data-variant="icon" className="@container rounded-xl border bg-card shadow-xs">
            <div className="flex flex-col gap-4 p-6">
                <div className="flex items-center gap-3">
                    <IconBadge icon={icon} />
                    <span className="text-body-sm text-muted-foreground">{label}</span>
                </div>
                <div className="flex flex-col gap-4 @xs:flex-row @xs:items-end @xs:justify-between">
                    <span className="text-h2 font-semibold">{value}</span>
                    {columns && <MiniColumns values={columns.values} label={columns.label} />}
                </div>
                {note && <p className="-mt-2 text-body-sm text-muted-foreground">{note}</p>}
                {delta && <StatDeltaLine delta={delta} />}
            </div>
        </div>
    );
}

interface ProgressStatCardProps {
    icon: LucideIcon;
    label: string;
    /** The headline reading, already formatted: "72%". */
    value: string;
    progress: { value: number; max: number; label: string; text: string };
}

/**
 * The progress card: one number against its goal, as a headline reading, a meter, and the same reading in words under it.
 */
export function ProgressStatCard({ icon, label, value, progress }: ProgressStatCardProps) {
    return (
        <div data-slot="stat-card" data-variant="progress" className="rounded-xl border bg-card shadow-xs">
            <div className="flex flex-col gap-4 p-6">
                <div className="flex items-center gap-3">
                    <IconBadge icon={icon} />
                    <span className="text-body-sm text-muted-foreground">{label}</span>
                </div>
                <span className="text-h2 font-semibold">{value}</span>
                <Meter value={progress.value} max={progress.max} label={progress.label} valueText={progress.text} />
                <p className="text-body-sm text-muted-foreground">{progress.text}</p>
            </div>
        </div>
    );
}

interface FeaturedStatCardProps {
    label: string;
    /** The one number a view leads with. Use at most one featured card in a view. */
    value: string;
    /** A small line under the number, in the card's own light text. */
    note?: string;
    delta?: StatDelta;
    trend?: { values: readonly number[]; label: string };
}

/**
 * The featured card: a deep, filled card with light text and the hero figure (48px, the same sans, proportional figures, kept on one line and stepping down to the 36px Display size on a phone), a fine gold
 * rule along the top, and a trend in light ink. Exactly one per view: it is the number the view is about.
 */
export function FeaturedStatCard({ label, value, note, delta, trend }: FeaturedStatCardProps) {
    return (
        <div
            data-slot="stat-card"
            data-variant="featured"
            className="@container relative overflow-hidden rounded-xl border border-transparent bg-primary text-primary-foreground shadow-md"
        >
            <span aria-hidden="true" className="absolute inset-x-0 top-0 h-0.5 bg-gold" />
            <div className="flex flex-col gap-4 p-6">
                <span className="text-body-sm opacity-80">{label}</span>
                <div className="flex flex-col gap-4 @xs:flex-row @xs:items-end @xs:justify-between">
                    <span className="text-hero font-semibold whitespace-nowrap max-sm:text-display">{value}</span>
                    {trend && <Sparkline values={trend.values} label={trend.label} inverse />}
                </div>
                {note && <p className="-mt-2 text-body-sm opacity-80">{note}</p>}
                {delta && <StatDeltaLine delta={delta} onFilled />}
            </div>
        </div>
    );
}
