import { Users } from 'lucide-react';
import { Link } from 'react-router';
import { MemberExportButton } from '@/components/Members/MemberExportButton';
import { MemberFilterBar } from '@/components/Members/MemberFilterBar';
import { MemberListItem } from '@/components/Members/MemberListItem';
import { MemberTable } from '@/components/Members/MemberTable';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/EmptyState';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { PageHeader } from '@/components/ui/PageHeader';
import { Pagination } from '@/components/ui/Pagination';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableSkeletonRows } from '@/components/ui/table';
import { WIDE_SCREEN_QUERY } from '@/constants/interaction';
import { PERMISSIONS } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { useCan } from '@/hooks/useCan';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { useMemberFilters } from '@/hooks/useMemberFilters';
import { useMembers } from '@/hooks/useMembers';

/** How many placeholder rows to show while the first page loads. */
const LOADING_ROWS = 6;

/**
 * The member directory: search and filter, then the members one tap from their profile: a table on a wide screen, with the photo,
 * number, phone, departments, status and View and Edit buttons, and a list of cards on a phone.
 * Every filter and the page number live in the URL, so a refresh or a shared link shows the same view.
 */
export function MembersPage() {
    const { filters, setFilter, setPage, clear } = useMemberFilters();
    const members = useMembers(filters);
    const canEdit = useCan(PERMISSIONS.MEMBERS_EDIT);
    const canExport = useCan(PERMISSIONS.MEMBERS_EXPORT);
    const wide = useMediaQuery(WIDE_SCREEN_QUERY);

    const addButton = canEdit ? (
        <Button asChild>
            <Link to={ROUTES.MEMBER_NEW}>Add member</Link>
        </Button>
    ) : undefined;

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                title="Members"
                description={members.data ? `${members.data.meta.total} found` : 'Everyone in your church'}
                actions={
                    canExport || addButton ? (
                        <>
                            {canEdit && (
                                <Button asChild variant="outline">
                                    <Link to={ROUTES.DEPARTMENTS}>Departments</Link>
                                </Button>
                            )}
                            {canExport && <MemberExportButton filters={filters} />}
                            {addButton}
                        </>
                    ) : undefined
                }
            />
            <MemberFilterBar filters={filters} onChange={setFilter} onClear={clear} />

            {members.isPending &&
                (wide ? (
                    <Table aria-busy="true">
                        <TableBody>
                            <TableSkeletonRows rows={LOADING_ROWS} columns={[false, false, false, false, false]} />
                        </TableBody>
                    </Table>
                ) : (
                    <div aria-busy="true" className="flex flex-col gap-2">
                        {Array.from({ length: LOADING_ROWS }, (_, row) => (
                            <Skeleton key={row} className="h-[76px] w-full rounded-xl" />
                        ))}
                    </div>
                ))}

            {members.isError && <ErrorNotice message={members.error.message} onRetry={() => void members.refetch()} />}

            {members.data && members.data.data.length === 0 && (
                <EmptyState
                    icon={Users}
                    title="No members found"
                    description="Try a different search, or clear the filters. Inactive and archived members only appear when you search or pick their status."
                    action={addButton}
                />
            )}

            {members.data && members.data.data.length > 0 && (
                <>
                    {wide ? (
                        <MemberTable members={members.data.data} canEdit={canEdit} refreshing={members.isPlaceholderData} />
                    ) : (
                        <ul className="flex flex-col gap-2" aria-live="polite">
                            {members.data.data.map((member) => (
                                <MemberListItem key={member.id} member={member} />
                            ))}
                        </ul>
                    )}
                    <Pagination
                        page={members.data.meta.current_page}
                        lastPage={members.data.meta.last_page}
                        onPageChange={setPage}
                        disabled={members.isPlaceholderData}
                    />
                </>
            )}
        </div>
    );
}
