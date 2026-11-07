import { AttendanceReportView } from '@/components/Reports/AttendanceReportView';
import { PageHeader } from '@/components/ui/PageHeader';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useUrlTab } from '@/hooks/useUrlTab';

/** The reports, one tab each. The tab is in the address. */
const TABS = ['attendance'] as const;

/**
 * The church's reports (story 5.13). Only staff with `reports.view` can open it (the server answers 403 to anyone else, and
 * the menu hides the link). Every figure is worked out by the server when asked, so it is always current.
 */
export function ReportsPage() {
    const [tab, selectTab] = useUrlTab(TABS);

    return (
        <div className="mx-auto flex max-w-3xl flex-col gap-4">
            <PageHeader title="Reports" description="How your church is doing" />
            <Tabs value={tab} onValueChange={selectTab}>
                <TabsList>
                    <TabsTrigger value="attendance">Attendance</TabsTrigger>
                </TabsList>
                <TabsContent value="attendance">
                    <AttendanceReportView />
                </TabsContent>
            </Tabs>
        </div>
    );
}
