import { Users } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ChangeStaffDialog } from '@/components/Staff/ChangeStaffDialog';
import { InviteStaffDialog } from '@/components/Staff/InviteStaffDialog';
import { StaffListItem } from '@/components/Staff/StaffListItem';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { EmptyState } from '@/components/ui/EmptyState';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { PageHeader } from '@/components/ui/PageHeader';
import { Pagination } from '@/components/ui/Pagination';
import { Skeleton } from '@/components/ui/skeleton';
import { useStaff, useStaffMutations } from '@/hooks/useStaff';
import { useStaffPage } from '@/hooks/useStaffPage';
import type { StaffMember } from '@/types/staff';

/** How many placeholder rows to show while the first page loads. */
const LOADING_ROWS = 3;

/**
 * The church's staff (story 5.15): invite someone, change their name, phone and role, and switch them off or on. Only the
 * church admin may open it (the server answers 403 to anyone else, and the menu hides the link). Switching someone off
 * asks first, because it signs them out at once; switching on does not. The page is in the address.
 */
export function StaffPage() {
    const { page, setPage } = useStaffPage();
    const staff = useStaff(page);
    const { setActive } = useStaffMutations();
    const [inviting, setInviting] = useState(false);
    const [changing, setChanging] = useState<StaffMember | undefined>(undefined);
    const [deactivating, setDeactivating] = useState<StaffMember | undefined>(undefined);

    const switchOnOrOff = (member: StaffMember, isActive: boolean, done: string) => {
        setActive.mutate(
            { id: member.id, isActive },
            {
                onSuccess: () => {
                    toast.success(done);
                    setDeactivating(undefined);
                },
                // The last church admin cannot be switched off; the server's own sentence says so.
                onError: (error) => {
                    toast.error(error.message);
                    setDeactivating(undefined);
                },
            },
        );
    };

    const toggle = (member: StaffMember) => {
        if (member.is_active) {
            setDeactivating(member);
        } else {
            switchOnOrOff(member, true, `${member.name} can sign in again.`);
        }
    };

    return (
        <div className="mx-auto flex max-w-3xl flex-col gap-4">
            <PageHeader
                title="Staff"
                description={staff.data ? `${staff.data.meta.total} staff` : 'Who can sign in to your church'}
                actions={<Button onClick={() => setInviting(true)}>Invite staff</Button>}
            />

            {staff.isPending && (
                <div aria-busy="true" className="flex flex-col gap-2">
                    {Array.from({ length: LOADING_ROWS }, (_, row) => (
                        <Skeleton key={row} className="h-[110px] w-full rounded-xl" />
                    ))}
                </div>
            )}

            {staff.isError && <ErrorNotice message={staff.error.message} onRetry={() => void staff.refetch()} />}

            {staff.data && staff.data.data.length === 0 && (
                <EmptyState icon={Users} title="No staff yet" description="People you invite will appear here." />
            )}

            {staff.data && staff.data.data.length > 0 && (
                <>
                    <ul className="flex flex-col gap-2" aria-live="polite">
                        {staff.data.data.map((member) => (
                            <StaffListItem key={member.id} staff={member} onChange={setChanging} onToggleActive={toggle} />
                        ))}
                    </ul>
                    <Pagination
                        page={staff.data.meta.current_page}
                        lastPage={staff.data.meta.last_page}
                        onPageChange={setPage}
                        disabled={staff.isPlaceholderData}
                    />
                </>
            )}

            <InviteStaffDialog open={inviting} onOpenChange={setInviting} />
            {/* Mounted only while there is someone to change, so each opening starts from that person. */}
            {changing !== undefined && <ChangeStaffDialog staff={changing} onClose={() => setChanging(undefined)} />}
            <ConfirmDialog
                open={deactivating !== undefined}
                onOpenChange={(open) => !open && setDeactivating(undefined)}
                title={`Deactivate ${deactivating?.name ?? ''}?`}
                description="They are signed out at once and cannot sign in until you reactivate them. Nothing they recorded is removed."
                confirmLabel="Deactivate"
                pending={setActive.isPending}
                onConfirm={() => deactivating !== undefined && switchOnOrOff(deactivating, false, `${deactivating.name} is deactivated.`)}
            />
        </div>
    );
}
