interface StatTileProps {
    /** What the number is, in plain words. */
    label: string;
    /** The number, already formatted. */
    value: string;
}

/**
 * One headline number with its label. The value uses the default proportional figures, because a large standalone number
 * set in equal-width digits looks loose.
 */
export function StatTile({ label, value }: StatTileProps) {
    return (
        <div className="flex flex-col gap-1 rounded-xl border p-3">
            <span className="text-sm text-muted-foreground">{label}</span>
            <span className="text-2xl font-semibold">{value}</span>
        </div>
    );
}
