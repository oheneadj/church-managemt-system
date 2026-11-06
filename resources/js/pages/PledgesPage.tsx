import { Target } from 'lucide-react';
import { useState } from 'react';
import { useSearchParams } from 'react-router';
import { PledgeDialog } from '@/components/Giving/PledgeDialog';
import { PledgeFilterBar } from '@/components/Giving/PledgeFilterBar';
import { PledgeListItem } from '@/components/Giving/PledgeListItem';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/EmptyState';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { PageHeader } from '@/components/ui/PageHeader';
import { Pagination } from '@/components/ui/Pagination';
import { Skeleton } from '@/components/ui/skeleton';
import { PERMISSIONS } from '@/constants/permissions';
import { useCan } from '@/hooks/useCan';
import { usePledgeFilters } from '@/hooks/usePledgeFilters';
import { usePledges } from '@/hooks/usePledges';
import type { Pledge } from '@/types/giving';

/** How many placeholder rows to show while the first page loads. */
const LOADING_ROWS = 4;

/**
 * The church's pledges, those due soonest first, with how far each has been paid. Staff who may record giving can make a
 * pledge and staff who may correct giving can change one. The filters and the page are in the address.
 */
export function PledgesPage() {
    const { filters, setFilter, setPage } = usePledgeFilters();
    const [, setParams] = useSearchParams();
    const pledges = usePledges(filters);
    const canMake = useCan(PERMISSIONS.GIVING_RECORD);
    const canChange = useCan(PERMISSIONS.GIVING_EDIT);
    const [making, setMaking] = useState(false);
    const [changing, setChanging] = useState<Pledge | undefined>(undefined);

    return (
        <div className="mx-auto flex max-w-3xl flex-col gap-4">
            <PageHeader
                title="Pledges"
                description={pledges.data ? `${pledges.data.meta.total} pledges found` : 'What members have promised to give'}
                actions={canMake ? <Button onClick={() => setMaking(true)}>New pledge</Button> : undefined}
            />
            <PledgeFilterBar filters={filters} onChange={setFilter} onClear={() => setParams({})} />

            {pledges.isPending && (
                <div aria-busy="true" className="flex flex-col gap-2">
                    {Array.from({ length: LOADING_ROWS }, (_, row) => (
                        <Skeleton key={row} className="h-[96px] w-full rounded-xl" />
                    ))}
                </div>
            )}

            {pledges.isError && <ErrorNotice message={pledges.error.message} onRetry={() => void pledges.refetch()} />}

            {pledges.data && pledges.data.data.length === 0 && (
                <EmptyState icon={Target} title="No pledges found" description="Pledges that members make will appear here." />
            )}

            {pledges.data && pledges.data.data.length > 0 && (
                <>
                    <ul className="flex flex-col gap-2" aria-live="polite">
                        {pledges.data.data.map((pledge) => (
                            <PledgeListItem key={pledge.id} pledge={pledge} onChange={canChange ? setChanging : undefined} />
                        ))}
                    </ul>
                    <Pagination
                        page={pledges.data.meta.current_page}
                        lastPage={pledges.data.meta.last_page}
                        onPageChange={setPage}
                        disabled={pledges.isPlaceholderData}
                    />
                </>
            )}

            <PledgeDialog open={making} onOpenChange={setMaking} />
            {/* Mounted only while there is a pledge to change, so closing never flashes the new-pledge form. */}
            {changing !== undefined && <PledgeDialog open onOpenChange={() => setChanging(undefined)} pledge={changing} />}
        </div>
    );
}
