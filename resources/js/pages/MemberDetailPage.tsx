import { useState } from 'react';
import { Link, useParams } from 'react-router';
import { ChangeStatusDialog } from '@/components/Members/ChangeStatusDialog';
import { MemberAttendanceTab } from '@/components/Members/MemberAttendanceTab';
import { MemberGivingTab } from '@/components/Members/MemberGivingTab';
import { MemberHeader } from '@/components/Members/MemberHeader';
import { MemberProfileTab } from '@/components/Members/MemberProfileTab';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { PERMISSIONS } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { useCan } from '@/hooks/useCan';
import { useMember } from '@/hooks/useMembers';
import { useUrlTab } from '@/hooks/useUrlTab';
import { ApiError } from '@/services/ApiError';
import { fullNameOf } from '@/utils/fullNameOf';

const TABS = ['profile', 'giving', 'attendance'] as const;

/**
 * One member's full record, in three tabs (Profile, Giving, Attendance). The selected tab is in the URL.
 * Staff who may edit see the photo, edit and status buttons, unless the member is deceased, whose record is read-only.
 */
export function MemberDetailPage() {
    const { id = '' } = useParams();
    const member = useMember(id);
    const canEdit = useCan(PERMISSIONS.MEMBERS_EDIT);
    const [tab, selectTab] = useUrlTab(TABS);
    const [changingStatus, setChangingStatus] = useState(false);

    if (member.isPending) {
        return <Skeleton aria-busy="true" className="mx-auto h-64 max-w-3xl rounded-xl" />;
    }

    if (member.isError) {
        const refused = member.error instanceof ApiError && (member.error.status === 404 || member.error.status === 403);

        return (
            <div className="mx-auto flex max-w-3xl flex-col gap-3">
                <ErrorNotice
                    message={refused ? 'This member was not found, or you do not have access to them.' : member.error.message}
                    onRetry={refused ? undefined : () => void member.refetch()}
                />
                <Link to={ROUTES.MEMBERS} className="text-sm underline underline-offset-4">
                    Back to members
                </Link>
            </div>
        );
    }

    const record = member.data;
    const fullName = fullNameOf(record);
    const editable = canEdit && record.status !== 'deceased';

    return (
        <div className="mx-auto flex max-w-3xl flex-col gap-4">
            <Link to={ROUTES.MEMBERS} className="text-sm underline underline-offset-4">
                ← All members
            </Link>
            <MemberHeader member={record} fullName={fullName} editable={editable} onChangeStatus={() => setChangingStatus(true)} />
            <Tabs value={tab} onValueChange={selectTab}>
                <TabsList>
                    <TabsTrigger value="profile">Profile</TabsTrigger>
                    <TabsTrigger value="giving">Giving</TabsTrigger>
                    <TabsTrigger value="attendance">Attendance</TabsTrigger>
                </TabsList>
                <TabsContent value="profile">
                    <MemberProfileTab member={record} editable={editable} />
                </TabsContent>
                <TabsContent value="giving">
                    <MemberGivingTab summary={record.giving_summary} />
                </TabsContent>
                <TabsContent value="attendance">
                    <MemberAttendanceTab summary={record.attendance_summary} />
                </TabsContent>
            </Tabs>
            {editable && <ChangeStatusDialog member={record} fullName={fullName} open={changingStatus} onOpenChange={setChangingStatus} />}
        </div>
    );
}
