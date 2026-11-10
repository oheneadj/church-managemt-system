import { UserXIcon } from 'lucide-react';
import { IconStatCard } from '@/components/Charts/StatCardVariants';
import { DashboardCard } from '@/components/Dashboard/DashboardCard';
import { ROUTES } from '@/constants/routes';
import { useAbsentMembers } from '@/hooks/useAbsentMembers';
import type { AbsentFilters } from '@/types/services';
import { formatCount } from '@/utils/formatCount';

/** The church's own setting, first page: only the total in its paging is read. */
const CHURCH_SETTING: AbsentFilters = { consecutive: null, page: 1 };

/** How many active members have gone absent, by the church's own setting. For staff who may read reports; it leads to the list. */
export function AbsentMembersCard() {
    const absent = useAbsentMembers(CHURCH_SETTING);

    return (
        <DashboardCard
            label="Absent members"
            to={ROUTES.ABSENT_MEMBERS}
            isPending={absent.isPending}
            error={absent.error}
            onRetry={() => void absent.refetch()}
        >
            <IconStatCard
                icon={UserXIcon}
                label="Absent members"
                value={formatCount(absent.data?.meta.total ?? 0)}
                note="Missed the last services in a row"
            />
        </DashboardCard>
    );
}
