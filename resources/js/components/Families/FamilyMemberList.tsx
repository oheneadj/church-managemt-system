import { Link } from 'react-router';
import { MemberStatusBadge } from '@/components/Members/MemberStatusBadge';
import { ROUTES } from '@/constants/routes';
import type { FamilyMember } from '@/types/members';

interface FamilyMemberListProps {
    members: readonly FamilyMember[];
}

/** The head of the household comes first, then everyone else by name. */
function headFirst(members: readonly FamilyMember[]): FamilyMember[] {
    return [...members].sort(
        (a, b) => Number(b.family_role === 'head') - Number(a.family_role === 'head') || a.full_name.localeCompare(b.full_name),
    );
}

/**
 * The members of a family, each one tap from their profile, with their role and status.
 */
export function FamilyMemberList({ members }: FamilyMemberListProps) {
    return (
        <ul className="flex flex-col gap-2">
            {headFirst(members).map((member) => (
                <li key={member.id}>
                    <Link
                        to={ROUTES.MEMBER(member.id)}
                        className="flex items-center gap-3 rounded-xl border p-3 transition-colors hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                    >
                        <div className="min-w-0 flex-1">
                            <p className="truncate font-medium">{member.full_name}</p>
                            <p className="truncate text-sm text-muted-foreground">
                                {member.member_number}
                                {member.family_role_label && ` · ${member.family_role_label}`}
                            </p>
                        </div>
                        <MemberStatusBadge status={member.status} label={member.status_label} />
                    </Link>
                </li>
            ))}
        </ul>
    );
}
