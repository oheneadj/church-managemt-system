import { HandCoins } from 'lucide-react';
import { useState } from 'react';
import { useSearchParams } from 'react-router';
import { ContributionFilterBar } from '@/components/Giving/ContributionFilterBar';
import { ContributionListItem } from '@/components/Giving/ContributionListItem';
import { VoidContributionDialog } from '@/components/Giving/VoidContributionDialog';
import { EmptyState } from '@/components/ui/EmptyState';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { PageHeader } from '@/components/ui/PageHeader';
import { Pagination } from '@/components/ui/Pagination';
import { Skeleton } from '@/components/ui/skeleton';
import { PERMISSIONS } from '@/constants/permissions';
import { useCan } from '@/hooks/useCan';
import { useContributionFilters } from '@/hooks/useContributionFilters';
import { useContributions } from '@/hooks/useContributions';
import type { Contribution } from '@/types/giving';

/** How many placeholder rows to show while the first page loads. */
const LOADING_ROWS = 5;

/**
 * The church's gifts, newest first, with filters by date, fund and how they were given. Staff who may correct giving can
 * void a gift. The filters and the page are in the address.
 */
export function GivingPage() {
    const { filters, setFilter, setPage } = useContributionFilters();
    const [, setParams] = useSearchParams();
    const gifts = useContributions(filters);
    const canVoid = useCan(PERMISSIONS.GIVING_EDIT);
    const [voiding, setVoiding] = useState<Contribution | undefined>(undefined);

    return (
        <div className="mx-auto flex max-w-3xl flex-col gap-4">
            <PageHeader
                title="Giving"
                description={gifts.data ? `${gifts.data.meta.total} gifts found` : 'Every gift the church has recorded'}
            />
            <ContributionFilterBar filters={filters} onChange={setFilter} onClear={() => setParams({})} />

            {gifts.isPending && (
                <div aria-busy="true" className="flex flex-col gap-2">
                    {Array.from({ length: LOADING_ROWS }, (_, row) => (
                        <Skeleton key={row} className="h-[64px] w-full rounded-xl" />
                    ))}
                </div>
            )}

            {gifts.isError && <ErrorNotice message={gifts.error.message} onRetry={() => void gifts.refetch()} />}

            {gifts.data && gifts.data.data.length === 0 && (
                <EmptyState icon={HandCoins} title="No gifts found" description="Gifts that are recorded will appear here." />
            )}

            {gifts.data && gifts.data.data.length > 0 && (
                <>
                    <ul className="flex flex-col gap-2" aria-live="polite">
                        {gifts.data.data.map((gift) => (
                            <ContributionListItem key={gift.id} gift={gift} onVoid={canVoid ? setVoiding : undefined} />
                        ))}
                    </ul>
                    <Pagination
                        page={gifts.data.meta.current_page}
                        lastPage={gifts.data.meta.last_page}
                        onPageChange={setPage}
                        disabled={gifts.isPlaceholderData}
                    />
                </>
            )}

            <VoidContributionDialog gift={voiding} onClose={() => setVoiding(undefined)} />
        </div>
    );
}
