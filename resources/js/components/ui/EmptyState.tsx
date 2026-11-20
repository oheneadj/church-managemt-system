import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

interface EmptyStateProps {
    icon: LucideIcon;
    title: string;
    description?: string;
    /** What the user can do next, for example a button to add the first record. */
    action?: ReactNode;
}

/**
 * What a list shows when there is nothing to list: say so plainly and offer the next step.
 */
export function EmptyState({ icon: Icon, title, description, action }: EmptyStateProps) {
    return (
        <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed p-8 text-center">
            <Icon aria-hidden="true" className="size-8 text-muted-foreground" />
            <h2 className="font-medium">{title}</h2>
            {description && <p className="max-w-sm text-sm text-muted-foreground">{description}</p>}
            {action}
        </div>
    );
}
