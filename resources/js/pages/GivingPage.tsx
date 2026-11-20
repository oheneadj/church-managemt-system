import { HandCoins } from 'lucide-react';
import { useState } from 'react';
import { useSearchParams } from 'react-router';
import { ContributionExportButton } from '@/components/Giving/ContributionExportButton';
import { ContributionFilterBar } from '@/components/Giving/ContributionFilterBar';
import { ContributionListItem } from '@/components/Giving/ContributionListItem';
import { GiftTable } from '@/components/Giving/GiftTable';
import { RecordGiftDialog } from '@/components/Giving/RecordGiftDialog';
import { VoidContributionDialog } from '@/components/Giving/VoidContributionDialog';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/EmptyState';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { PageHeader } from '@/components/ui/PageHeader';
import { Pagination } from '@/components/ui/Pagination';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableSkeletonRows } from '@/components/ui/table';
import { WIDE_SCREEN_QUERY } from '@/constants/interaction';
import { PERMISSIONS } from '@/constants/permissions';
import { useCan } from '@/hooks/useCan';
import { useContributionFilters } from '@/hooks/useContributionFilters';
import { useContributions } from '@/hooks/useContributions';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import type { Contribution } from '@/types/giving';

/** How many placeholder rows to show while the first page loads. */
const LOADING_ROWS = 5;

/**
 * The church's gifts, newest first, as a table on a wide screen and a list of cards on a phone, with filters by date, fund and how they were given. Staff who may correct giving can
 * void a gift, and staff who may record giving can add a cash gift. The filters and the page are in the address.
 */
export function GivingPage() {
    const { filters, setFilter, setPage } = useContributionFilters();
    const [, setParams] = useSearchParams();
    const gifts = useContributions(filters);
    const canVoid = useCan(PERMISSIONS.GIVING_EDIT);
    const canRecord = useCan(PERMISSIONS.GIVING_RECORD);
    const canExport = useCan(PERMISSIONS.GIVING_EXPORT);
    const wide = useMediaQuery(WIDE_SCREEN_QUERY);
    const [recording, setRecording] = useState(false);
    const [voiding, setVoiding] = useState<Contribution | undefined>(undefined);

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                title="Giving"
                description={gifts.data ? `${gifts.data.meta.total} gifts found` : 'Every gift the church has recorded'}
                actions={
                    canExport || canRecord ? (
                        <>
                            {canExport && <ContributionExportButton filters={filters} />}
                            {canRecord && <Button onClick={() => setRecording(true)}>Record a gift</Button>}
                        </>
                    ) : undefined
                }
            />
            <ContributionFilterBar filters={filters} onChange={setFilter} onClear={() => setParams({})} />

            {gifts.isPending &&
                (wide ? (
                    <Table aria-busy="true">
                        <TableBody>
                            <TableSkeletonRows rows={LOADING_ROWS} columns={[false, false, false, false, true, false]} />
                        </TableBody>
                    </Table>
                ) : (
                    <div aria-busy="true" className="flex flex-col gap-2">
                        {Array.from({ length: LOADING_ROWS }, (_, row) => (
                            <Skeleton key={row} className="h-[64px] w-full rounded-xl" />
                        ))}
                    </div>
                ))}

            {gifts.isError && <ErrorNotice message={gifts.error.message} onRetry={() => void gifts.refetch()} />}

            {gifts.data && gifts.data.data.length === 0 && (
                <EmptyState icon={HandCoins} title="No gifts found" description="Gifts that are recorded will appear here." />
            )}

            {gifts.data && gifts.data.data.length > 0 && (
                <>
                    {wide ? (
                        <GiftTable gifts={gifts.data.data} onVoid={canVoid ? setVoiding : undefined} refreshing={gifts.isPlaceholderData} />
                    ) : (
                        <ul className="flex flex-col gap-2" aria-live="polite">
                            {gifts.data.data.map((gift) => (
                                <ContributionListItem key={gift.id} gift={gift} onVoid={canVoid ? setVoiding : undefined} />
                            ))}
                        </ul>
                    )}
                    <Pagination
                        page={gifts.data.meta.current_page}
                        lastPage={gifts.data.meta.last_page}
                        onPageChange={setPage}
                        disabled={gifts.isPlaceholderData}
                    />
                </>
            )}

            <RecordGiftDialog open={recording} onOpenChange={setRecording} />
            <VoidContributionDialog gift={voiding} onClose={() => setVoiding(undefined)} />
        </div>
    );
}
