import { ActivityFeed } from '@/components/Dashboard/ActivityFeed';
import { AbsentMembersCard } from '@/components/Dashboard/AbsentMembersCard';
import { GivingCard } from '@/components/Dashboard/GivingCard';
import { LastServiceCard } from '@/components/Dashboard/LastServiceCard';
import { MembersCard } from '@/components/Dashboard/MembersCard';
import { PERMISSIONS } from '@/constants/permissions';
import { useCan } from '@/hooks/useCan';
import { useAuthStore } from '@/stores/useAuthStore';
import { useChurchStore } from '@/stores/useChurchStore';

/**
 * The home page (story 5.10): a few summary cards that suit the signed-in person's role, each leading to the page behind it.
 * The active members are for everyone. The last service's attendance and the members who have gone absent are for staff who
 * may read reports, and this month's giving for staff who may read giving totals. A card a person may not see is not drawn,
 * and the server refuses it anyway. Below them, staff who may read reports see the recent activity in plain sentences; the
 * server shows each person only the lines they may see (D-068).
 */
export function DashboardPage() {
    const user = useAuthStore((state) => state.user);
    const church = useChurchStore((state) => state.church);
    const canSeeAttendance = useCan(PERMISSIONS.REPORTS_VIEW);
    const canSeeGiving = useCan(PERMISSIONS.GIVING_SUMMARY);

    return (
        <section aria-labelledby="dashboard-heading" className="mx-auto flex max-w-3xl flex-col gap-4">
            <header>
                <h1 id="dashboard-heading" className="text-xl font-semibold">
                    Welcome, {user?.name}
                </h1>
                <p className="mt-1 text-sm text-muted-foreground">Here is how {church?.name} is doing.</p>
            </header>
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <MembersCard />
                {canSeeAttendance && <LastServiceCard />}
                {canSeeAttendance && <AbsentMembersCard />}
                {canSeeGiving && <GivingCard />}
            </div>
            {canSeeAttendance && <ActivityFeed />}
        </section>
    );
}
