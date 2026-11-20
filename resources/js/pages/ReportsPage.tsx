import { ChartColumn } from 'lucide-react';
import { AttendanceReportView } from '@/components/Reports/AttendanceReportView';
import { GivingReportView } from '@/components/Reports/GivingReportView';
import { MemberGrowthView } from '@/components/Reports/MemberGrowthView';
import { EmptyState } from '@/components/ui/EmptyState';
import { PageHeader } from '@/components/ui/PageHeader';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { PERMISSIONS } from '@/constants/permissions';
import { useCan } from '@/hooks/useCan';
import { useUrlTab } from '@/hooks/useUrlTab';

/** The reports a person may open, one tab each. The tab is in the address. */
type ReportTab = 'attendance' | 'members' | 'giving';

/** The tabs this person may open, for the tab bar: the server checks each report again, so these only hide what it would refuse. */
function ReportTabs({ tabs }: { tabs: readonly [ReportTab, ...ReportTab[]] }) {
    const [tab, selectTab] = useUrlTab(tabs);

    return (
        <Tabs value={tab} onValueChange={selectTab}>
            <TabsList>
                {tabs.includes('attendance') && <TabsTrigger value="attendance">Attendance</TabsTrigger>}
                {tabs.includes('members') && <TabsTrigger value="members">Members</TabsTrigger>}
                {tabs.includes('giving') && <TabsTrigger value="giving">Giving</TabsTrigger>}
            </TabsList>
            {tabs.includes('attendance') && (
                <TabsContent value="attendance">
                    <AttendanceReportView />
                </TabsContent>
            )}
            {tabs.includes('members') && (
                <TabsContent value="members">
                    <MemberGrowthView />
                </TabsContent>
            )}
            {tabs.includes('giving') && (
                <TabsContent value="giving">
                    <GivingReportView />
                </TabsContent>
            )}
        </Tabs>
    );
}

/**
 * The church's reports (story 5.13). The attendance report needs `reports.view` and the giving report `giving.summary` (D-046):
 * each tab appears only for staff who hold its permission, and the server answers 403 to anyone else. Every figure is worked
 * out by the server when asked, so it is always current.
 */
export function ReportsPage() {
    const canSeeAttendance = useCan(PERMISSIONS.REPORTS_VIEW);
    const canSeeGiving = useCan(PERMISSIONS.GIVING_SUMMARY);
    const tabs: ReportTab[] = [
        ...(canSeeAttendance ? (['attendance', 'members'] as const) : []),
        ...(canSeeGiving ? (['giving'] as const) : []),
    ];
    const [first, ...rest] = tabs;

    return (
        <div className="mx-auto flex max-w-3xl flex-col gap-4">
            <PageHeader title="Reports" description="How your church is doing" />
            {first === undefined ? (
                <EmptyState
                    icon={ChartColumn}
                    title="No reports for you"
                    description="Ask your church admin if you need to see the reports."
                />
            ) : (
                <ReportTabs tabs={[first, ...rest]} />
            )}
        </div>
    );
}
