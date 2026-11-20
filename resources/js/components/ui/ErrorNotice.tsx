import { Button } from '@/components/ui/button';

interface ErrorNoticeProps {
    message: string;
    /** When given, a "Try again" button is shown. */
    onRetry?: () => void;
}

/**
 * A plain message for a request that failed, announced to screen readers, with an optional retry.
 */
export function ErrorNotice({ message, onRetry }: ErrorNoticeProps) {
    return (
        <div
            role="alert"
            className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
        >
            <span>{message}</span>
            {onRetry && (
                <Button variant="outline" size="sm" onClick={onRetry}>
                    Try again
                </Button>
            )}
        </div>
    );
}
