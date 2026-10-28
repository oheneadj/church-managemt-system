import { Link } from 'react-router';
import { ROUTES } from '@/constants/routes';
import type { Family } from '@/types/members';

interface FamilyListItemProps {
    family: Family;
}

/**
 * One family in the list: its name and how many members you may see, one tap to open it.
 */
export function FamilyListItem({ family }: FamilyListItemProps) {
    const count = family.members_count ?? 0;

    return (
        <li>
            <Link
                to={ROUTES.FAMILY(family.id)}
                className="flex items-center justify-between gap-3 rounded-xl border p-3 transition-colors hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                <span className="min-w-0 truncate font-medium">{family.name}</span>
                <span className="shrink-0 text-sm text-muted-foreground">
                    {count} {count === 1 ? 'member' : 'members'}
                </span>
            </Link>
        </li>
    );
}
