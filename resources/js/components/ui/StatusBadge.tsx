import { Badge } from '@/components/ui/badge';

export type StatusTone = 'success' | 'neutral' | 'warning' | 'danger';

interface StatusBadgeProps {
    /** The text to show. It carries the meaning; colour only backs it up. */
    label: string;
    tone: StatusTone;
}

const TONE_CLASSES: Record<StatusTone, string> = {
    success: 'bg-feedback-success-surface text-feedback-success',
    neutral: 'bg-muted text-foreground',
    warning: 'bg-feedback-warning-surface text-feedback-warning',
    danger: 'bg-feedback-error-surface text-feedback-error',
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
