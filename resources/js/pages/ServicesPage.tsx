import { CalendarDays } from 'lucide-react';
import { useState } from 'react';
import { Link, useNavigate } from 'react-router';
import { SelectField } from '@/components/form/SelectField';
import { ServiceFormDialog } from '@/components/Services/ServiceFormDialog';
import { ServiceListItem } from '@/components/Services/ServiceListItem';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/EmptyState';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { PageHeader } from '@/components/ui/PageHeader';
import { Pagination } from '@/components/ui/Pagination';
import { Skeleton } from '@/components/ui/skeleton';
import { SERVICE_TYPES } from '@/constants/enums';
import { PERMISSIONS } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { useCan } from '@/hooks/useCan';
import { useServiceFilters } from '@/hooks/useServiceFilters';
import { useServices } from '@/hooks/useServices';

/** How many placeholder rows to show while the first page loads. */
const LOADING_ROWS = 5;

/**
 * The church's services, newest first, filtered by type, with a button for staff who may create one.
 * The filter and page are in the URL.
 */
export function ServicesPage() {
    const { filters, setType, setPage } = useServiceFilters();
    const services = useServices(filters);
    const canEdit = useCan(PERMISSIONS.MEMBERS_EDIT);
    const navigate = useNavigate();
    const [creating, setCreating] = useState(false);

    const newButton = canEdit ? (
        <Button size="lg" onClick={() => setCreating(true)}>
            New service
        </Button>
    ) : undefined;
    const headerActions = canEdit ? (
        <>
            <Button asChild size="lg" variant="outline">
                <Link to={ROUTES.SERVICE_SCHEDULES}>Weekly schedules</Link>
            </Button>
            {newButton}
        </>
    ) : undefined;

    return (
        <div className="mx-auto flex max-w-3xl flex-col gap-4">
            <PageHeader
                title="Services"
                description={services.data ? `${services.data.meta.total} found` : 'Services and events'}
                actions={headerActions}
            />
            <SelectField
                id="service-type-filter"
                label="Type"
                options={SERVICE_TYPES}
                emptyLabel="All types"
                value={filters.type}
                onChange={(event) => setType(event.target.value)}
            />

            {services.isPending && (
                <div aria-busy="true" className="flex flex-col gap-2">
                    {Array.from({ length: LOADING_ROWS }, (_, row) => (
                        <Skeleton key={row} className="h-[64px] w-full rounded-xl" />
                    ))}
                </div>
            )}

            {services.isError && <ErrorNotice message={services.error.message} onRetry={() => void services.refetch()} />}

            {services.data && services.data.data.length === 0 && (
                <EmptyState
                    icon={CalendarDays}
                    title="No services found"
                    description="Create the first service to start recording who comes."
                    action={newButton}
                />
            )}

            {services.data && services.data.data.length > 0 && (
                <>
                    <ul className="flex flex-col gap-2" aria-live="polite">
                        {services.data.data.map((service) => (
                            <ServiceListItem key={service.id} service={service} />
                        ))}
                    </ul>
                    <Pagination
                        page={services.data.meta.current_page}
                        lastPage={services.data.meta.last_page}
                        onPageChange={setPage}
                        disabled={services.isPlaceholderData}
                    />
                </>
            )}

            {canEdit && (
                <ServiceFormDialog
                    open={creating}
                    onOpenChange={setCreating}
                    onSaved={(service) => void navigate(ROUTES.SERVICE(service.id))}
                />
            )}
        </div>
    );
}
