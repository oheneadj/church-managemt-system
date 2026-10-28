import type { ReactNode } from 'react';

interface PageHeaderProps {
    title: string;
    description?: string;
    /** Buttons shown beside the title, for example "Add member". */
    actions?: ReactNode;
}

/**
 * The title block at the top of a page: one h1, an optional line under it, and the page's main actions.
 */
export function PageHeader({ title, description, actions }: PageHeaderProps) {
    return (
        <header className="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 className="text-xl font-semibold">{title}</h1>
                {description && <p className="mt-1 text-sm text-muted-foreground">{description}</p>}
            </div>
            {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
        </header>
    );
}
