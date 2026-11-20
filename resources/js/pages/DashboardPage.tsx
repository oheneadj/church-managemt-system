import { ActivityFeed } from '@/components/Dashboard/ActivityFeed';
import { AttendanceChartCard } from '@/components/Dashboard/AttendanceChartCard';
import { AbsentMembersCard } from '@/components/Dashboard/AbsentMembersCard';
import { GivingCard } from '@/components/Dashboard/GivingCard';
import { GivingChartCard } from '@/components/Dashboard/GivingChartCard';
import { LastServiceCard } from '@/components/Dashboard/LastServiceCard';
import { MembersCard } from '@/components/Dashboard/MembersCard';
import { PERMISSIONS } from '@/constants/permissions';
import { useCan } from '@/hooks/useCan';
import { useAuthStore } from '@/stores/useAuthStore';
import { useChurchStore } from '@/stores/useChurchStore';

/**
 * The home page (story 5.10): a few summary cards that suit the signed-in person's role, each leading to the page behind it, in the
 * design system's card designs: this month's giving as the one featured card, the members and the absent members as icon cards, and
 * the last service as a trend card. Below them, charts of recent attendance and giving, each with a table of the same figures.
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
        <section aria-labelledby="dashboard-heading" className="flex flex-col gap-8">
            <header>
                <h1 id="dashboard-heading" className="text-h2 font-semibold md:text-h1">
                    Welcome, {user?.name}
                </h1>
                <p className="mt-1 text-body text-muted-foreground">Here is how {church?.name} is doing.</p>
            </header>
            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                {canSeeGiving && (
                    <div className="sm:col-span-2 lg:col-span-3">
                        <GivingCard />
                    </div>
                )}
                <MembersCard />
                {canSeeAttendance && <LastServiceCard />}
                {canSeeAttendance && <AbsentMembersCard />}
            </div>
            {(canSeeAttendance || canSeeGiving) && (
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {canSeeAttendance && <AttendanceChartCard />}
                    {canSeeGiving && <GivingChartCard />}
                </div>
            )}
            {canSeeAttendance && <ActivityFeed />}
        </section>
    );
}
