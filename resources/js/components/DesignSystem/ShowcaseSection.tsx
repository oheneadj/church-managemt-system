import type { ReactNode } from 'react';

interface ShowcaseSectionProps {
    id: string;
    title: string;
    description: string;
    children: ReactNode;
}

/**
 * One titled block of the showcase page: a heading, one sentence on what to look for, and the demonstrations.
 */
export function ShowcaseSection({ id, title, description, children }: ShowcaseSectionProps) {
    return (
        <section aria-labelledby={`${id}-title`} className="flex flex-col gap-4">
            <div>
                <h2 id={`${id}-title`} className="text-lg font-semibold">
                    {title}
                </h2>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
            {children}
        </section>
    );
}
