import type { ReactNode } from 'react';

interface DetailItem {
    label: string;
    /** What to show. Missing values (null, empty text) are shown as a dash. */
    value: ReactNode;
}

interface DetailListProps {
    items: readonly DetailItem[];
}

/**
 * Labels and values as a description list: the right element for facts about one record.
 */
export function DetailList({ items }: DetailListProps) {
    return (
        <dl className="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
            {items.map((item) => (
                <div key={item.label}>
                    <dt className="text-xs text-muted-foreground">{item.label}</dt>
                    <dd className="text-sm">{item.value === null || item.value === '' ? '—' : item.value}</dd>
                </div>
            ))}
        </dl>
    );
}
