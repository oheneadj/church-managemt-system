import { UsersRound } from 'lucide-react';
import { useState } from 'react';
import { useNavigate } from 'react-router';
import { FamilyFormDialog } from '@/components/Families/FamilyFormDialog';
import { FamilyListItem } from '@/components/Families/FamilyListItem';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/EmptyState';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { PageHeader } from '@/components/ui/PageHeader';
import { Pagination } from '@/components/ui/Pagination';
import { SearchBox } from '@/components/ui/SearchBox';
import { Skeleton } from '@/components/ui/skeleton';
import { PERMISSIONS } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { useCan } from '@/hooks/useCan';
import { useFamilies } from '@/hooks/useFamilies';
import { useFamilyFilters } from '@/hooks/useFamilyFilters';

/** How many placeholder rows to show while the first page loads. */
const LOADING_ROWS = 5;

/**
 * The families of the church, searchable by name, with a button to create one. The search and page are in the URL.
 */
export function FamiliesPage() {
    const { filters, setSearch, setPage } = useFamilyFilters();
    const families = useFamilies(filters);
    const canEdit = useCan(PERMISSIONS.MEMBERS_EDIT);
    const navigate = useNavigate();
    const [creating, setCreating] = useState(false);

    const newButton = canEdit ? (
        <Button size="lg" onClick={() => setCreating(true)}>
            New family
        </Button>
    ) : undefined;

    return (
        <div className="mx-auto flex max-w-3xl flex-col gap-4">
            <PageHeader
                title="Families"
                description={families.data ? `${families.data.meta.total} found` : 'Households in your church'}
                actions={newButton}
            />
            <SearchBox id="family-search" label="Search families" placeholder="Family name" value={filters.search} onSearch={setSearch} />

            {families.isPending && (
                <div aria-busy="true" className="flex flex-col gap-2">
                    {Array.from({ length: LOADING_ROWS }, (_, row) => (
                        <Skeleton key={row} className="h-[52px] w-full rounded-xl" />
                    ))}
                </div>
            )}

            {families.isError && <ErrorNotice message={families.error.message} onRetry={() => void families.refetch()} />}

            {families.data && families.data.data.length === 0 && (
                <EmptyState
                    icon={UsersRound}
                    title="No families found"
                    description="Try a different name, or create the first family."
                    action={newButton}
                />
            )}

            {families.data && families.data.data.length > 0 && (
                <>
                    <ul className="flex flex-col gap-2" aria-live="polite">
                        {families.data.data.map((family) => (
                            <FamilyListItem key={family.id} family={family} />
                        ))}
                    </ul>
                    <Pagination
                        page={families.data.meta.current_page}
                        lastPage={families.data.meta.last_page}
                        onPageChange={setPage}
                        disabled={families.isPlaceholderData}
                    />
                </>
            )}

            {canEdit && (
                <FamilyFormDialog
                    open={creating}
                    onOpenChange={setCreating}
                    onSaved={(family) => void navigate(ROUTES.FAMILY(family.id))}
                />
            )}
        </div>
    );
}
