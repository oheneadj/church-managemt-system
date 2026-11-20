import type { ReactNode } from 'react';
import { Link } from 'react-router';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from 'cn';

interface DashboardCardProps {
    /** What the number is, in plain words. Used for the loading and failure messages. */
    label: string;
    /** The page the card leads to, so a number is one tap from the detail behind it. */
    to: string;
    /** True while the first figure is on its way. */
    isPending: boolean;
    /** Set when the figure could not be had: the card then says so and offers to try again. */
    error?: Error | null;
    onRetry: () => void;
    /** How tall the placeholder is while loading, so the page does not jump when the card arrives. */
    placeholderClassName?: string;
    /** The stat card itself. Only drawn when there is neither a wait nor an error. */
    children: ReactNode;
}

/**
 * One summary card on the home page. It is a link, so the number leads to the page that explains it, and it has its own
 * loading and failure states, so a slow or broken report never holds back the other cards.
 */
export function DashboardCard({ label, to, isPending, error, onRetry, placeholderClassName = 'h-40', children }: DashboardCardProps) {
    if (isPending) {
        return <Skeleton aria-busy="true" aria-label={`${label} is loading`} className={cn('w-full rounded-xl', placeholderClassName)} />;
    }

    if (error) {
        return (
            <div
                role="alert"
                className="flex flex-col gap-3 rounded-xl border border-feedback-error-border bg-feedback-error-surface p-6 text-body-sm text-feedback-error"
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
        <Link
            to={to}
            className="block rounded-xl outline-none transition-shadow hover:shadow-md focus-visible:ring-3 focus-visible:ring-ring"
        >
            {children}
        </Link>
    );
}
