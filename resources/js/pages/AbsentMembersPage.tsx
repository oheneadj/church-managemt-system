import { UserRoundCheck } from 'lucide-react';
import { AbsentFilterBar } from '@/components/Absent/AbsentFilterBar';
import { AbsentMemberListItem } from '@/components/Absent/AbsentMemberListItem';
import { EmptyState } from '@/components/ui/EmptyState';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { PageHeader } from '@/components/ui/PageHeader';
import { Pagination } from '@/components/ui/Pagination';
import { Skeleton } from '@/components/ui/skeleton';
import { useAbsentFilters } from '@/hooks/useAbsentFilters';
import { useAbsentMembers } from '@/hooks/useAbsentMembers';
import { useChurchSettings } from '@/hooks/useChurchSettings';

/** How many placeholder rows to show while the first page loads. */
const LOADING_ROWS = 4;

/**
 * Active members who have missed the last few Sunday services in a row, so someone can phone them (docs/02, QA-05 TC-05-12).
 * It is worked out when asked, so it is always up to date. Only Sunday services that someone was checked in to count, and a
 * member who joined after the oldest of them is left out. Only staff with `reports.view` can open it (the server answers 403
 * to anyone else). The number of services and the page are in the address.
 */
export function AbsentMembersPage() {
    const { filters, setConsecutive, setPage } = useAbsentFilters();
    const absent = useAbsentMembers(filters);
    const settings = useChurchSettings();

    return (
        <div className="mx-auto flex max-w-3xl flex-col gap-4">
            <PageHeader
                title="Absent members"
                description={absent.data ? `${absent.data.meta.total} found` : 'Members who have stopped coming'}
            />
            <AbsentFilterBar consecutive={filters.consecutive} churchSetting={settings.data?.absence_threshold} onChange={setConsecutive} />

            {absent.isPending && (
                <div aria-busy="true" className="flex flex-col gap-2">
                    {Array.from({ length: LOADING_ROWS }, (_, row) => (
                        <Skeleton key={row} className="h-[64px] w-full rounded-xl" />
                    ))}
                </div>
            )}

            {absent.isError && <ErrorNotice message={absent.error.message} onRetry={() => void absent.refetch()} />}

            {absent.data && absent.data.data.length === 0 && (
                <EmptyState
                    icon={UserRoundCheck}
                    title="Nobody has gone absent"
                    description="No active member has missed all of the last few Sunday services. Until that many Sunday services have been recorded, nobody can be listed."
                />
            )}

            {absent.data && absent.data.data.length > 0 && (
                <>
                    <ul className="flex flex-col gap-2" aria-live="polite">
                        {absent.data.data.map((member) => (
                            <AbsentMemberListItem key={member.id} member={member} />
                        ))}
                    </ul>
                    <Pagination
                        page={absent.data.meta.current_page}
                        lastPage={absent.data.meta.last_page}
                        onPageChange={setPage}
                        disabled={absent.isPlaceholderData}
                    />
                </>
            )}
        </div>
    );
}
