import { Link } from 'react-router';
import { ROUTES } from '@/constants/routes';
import type { AbsentMember } from '@/types/services';
import { formatDate } from '@/utils/formatDate';

/**
 * One member who has gone absent: their name (one tap to their profile), number, a phone link so they can be called straight
 * away, and when they were last at church. A member who has never been checked in says so.
 */
export function AbsentMemberListItem({ member }: { member: AbsentMember }) {
    return (
        <li className="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3">
            <span className="min-w-0">
                <Link to={ROUTES.MEMBER(member.id)} className="block truncate font-medium underline-offset-4 hover:underline">
                    {member.full_name}
                </Link>
                <span className="block text-sm text-muted-foreground">
                    {member.member_number} ·{' '}
                    <a href={`tel:${member.phone}`} className="underline underline-offset-4">
                        {member.phone}
                    </a>
                </span>
            </span>
            <span className="text-sm text-muted-foreground">
                {member.last_attended_at === null ? 'Not checked in yet' : `Last at church ${formatDate(member.last_attended_at)}`}
            </span>
        </li>
    );
}
