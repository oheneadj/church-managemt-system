import { Link } from 'react-router';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { StatTile } from '@/components/ui/StatTile';

interface DashboardCardProps {
    /** What the number is, in plain words. */
    label: string;
    /** The page the card leads to, so a number is one tap from the detail behind it. */
    to: string;
    /** True while the first figure is on its way. */
    isPending: boolean;
    /** Set when the figure could not be had: the card then says so and offers to try again. */
    error?: Error | null;
    onRetry: () => void;
    /** The number, already formatted, and the small line under it. Only read when there is neither a wait nor an error. */
    value: string;
    note?: string;
}

/**
 * One summary card on the home page. It is a link, so the number leads to the page that explains it, and it has its own
 * loading and failure states, so a slow or broken report never holds back the other cards.
 */
export function DashboardCard({ label, to, isPending, error, onRetry, value, note }: DashboardCardProps) {
    if (isPending) {
        return <Skeleton aria-busy="true" aria-label={`${label} is loading`} className="h-[96px] w-full rounded-xl" />;
    }

    if (error) {
        return (
            <div
                role="alert"
                className="flex flex-col gap-2 rounded-xl border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
            >
                <span>
                    {label}: {error.message}
                </span>
                <Button variant="outline" size="sm" className="self-start" onClick={onRetry}>
                    Try again
                </Button>
            </div>
        );
    }

    return (
        <Link to={to} className="rounded-xl outline-none hover:bg-muted/40 focus-visible:ring-3 focus-visible:ring-ring">
            <StatTile label={label} value={value} note={note} />
        </Link>
    );
}
