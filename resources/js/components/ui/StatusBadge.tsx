import { Badge } from '@/components/ui/badge';

export type StatusTone = 'success' | 'neutral' | 'warning' | 'danger';

interface StatusBadgeProps {
    /** The text to show. It carries the meaning; colour only backs it up. */
    label: string;
    tone: StatusTone;
}

const TONE_CLASSES: Record<StatusTone, string> = {
    success: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    neutral: 'bg-muted text-foreground',
    warning: 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
    danger: 'bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-200',
};

/**
 * A small coloured label for any status. The word is always shown, so the meaning never depends on colour alone.
 */
export function StatusBadge({ label, tone }: StatusBadgeProps) {
    return (
        <Badge variant="secondary" className={TONE_CLASSES[tone]}>
            {label}
        </Badge>
    );
}
