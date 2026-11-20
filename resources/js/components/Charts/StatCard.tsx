import { Sparkline } from '@/components/Charts/Sparkline';
import { StatDeltaLine, type StatDelta } from '@/components/Charts/StatDeltaLine';

export type { StatDelta };

interface StatCardProps {
    /** What the number is, in sentence case, with no colon. */
    label: string;
    /** The number, already formatted. Proportional figures: a large standalone number set in equal-width digits looks loose. */
    value: string;
    /** A small line under the number: what it is about, or which one it was. */
    note?: string;
    delta?: StatDelta;
    trend?: { values: readonly number[]; label: string };
}

/**
 * The trend card: the label, the number, a change against a named period, and a small trend line. The change is always an arrow
 * plus signed words, never colour alone; the colour is what the change means. The card is its own container, so the trend sits
 * beside the number when the card is wide enough and drops under it when it is not. Other designs: `IconStatCard`,
 * `ProgressStatCard` and `FeaturedStatCard` (StatCardVariants).
 */
export function StatCard({ label, value, note, delta, trend }: StatCardProps) {
    return (
        <div data-slot="stat-card" data-variant="trend" className="@container rounded-xl border bg-card shadow-xs">
            <div className="flex flex-col gap-4 p-6">
                <span className="text-body-sm text-muted-foreground">{label}</span>
                <div className="flex flex-col gap-4 @xs:flex-row @xs:items-end @xs:justify-between">
                    <span className="text-h2 font-semibold">{value}</span>
                    {trend && <Sparkline values={trend.values} label={trend.label} />}
                </div>
                {note && <p className="-mt-2 text-body-sm text-muted-foreground">{note}</p>}
                {delta && <StatDeltaLine delta={delta} />}
            </div>
        </div>
    );
}
