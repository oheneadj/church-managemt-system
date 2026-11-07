import { DashboardCard } from '@/components/Dashboard/DashboardCard';
import { ROUTES } from '@/constants/routes';
import { useMembers } from '@/hooks/useMembers';
import type { MemberFilters } from '@/types/members';
import { formatCount } from '@/utils/formatCount';

/** The active members, first page: only the total in its paging is read. */
const ACTIVE_MEMBERS: MemberFilters = { search: '', status: 'active', gender: '', department_id: '', page: 1 };

/**
 * How many active members the signed-in person can see (a department head sees their own department, the church admin
 * everyone). It leads to the active members in the member list. Every role may open it.
 */
export function MembersCard() {
    const members = useMembers(ACTIVE_MEMBERS);

    return (
        <DashboardCard
            label="Active members"
            to={`${ROUTES.MEMBERS}?status=active`}
            isPending={members.isPending}
            error={members.error}
            onRetry={() => void members.refetch()}
            value={formatCount(members.data?.meta.total ?? 0)}
            note="Members who are active now"
        />
    );
}
