import { Link } from 'react-router';
import { MemberAvatar } from '@/components/Members/MemberAvatar';
import { MemberStatusBadge } from '@/components/Members/MemberStatusBadge';
import { ROUTES } from '@/constants/routes';
import type { MemberSummary } from '@/types/members';

interface MemberListItemProps {
    member: MemberSummary;
}

/**
 * One member in the directory: photo, name, number, phone and status, all one tap to the profile.
 * It stacks neatly on a phone and spreads out on a wide screen.
 */
export function MemberListItem({ member }: MemberListItemProps) {
    return (
        <li>
            <Link
                to={ROUTES.MEMBER(member.id)}
                className="flex items-center gap-3 rounded-xl border p-3 transition-colors hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                <MemberAvatar fullName={member.full_name} photoUrl={member.photo_url} className="size-12" />
                <div className="min-w-0 flex-1">
                    <p className="truncate font-medium">{member.full_name}</p>
                    <p className="truncate text-sm text-muted-foreground">
                        {member.member_number} · {member.phone}
                    </p>
                    {member.departments.length > 0 && (
                        <p className="truncate text-xs text-muted-foreground">{member.departments.join(', ')}</p>
                    )}
                </div>
                <MemberStatusBadge status={member.status} label={member.status_label} />
            </Link>
        </li>
    );
}
