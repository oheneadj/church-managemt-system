import { CalendarClock } from 'lucide-react';
import { useState } from 'react';
import { Link, useSearchParams } from 'react-router';
import { toast } from 'sonner';
import { ServiceScheduleDialog } from '@/components/Services/ServiceScheduleDialog';
import { ServiceScheduleListItem } from '@/components/Services/ServiceScheduleListItem';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/EmptyState';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { PageHeader } from '@/components/ui/PageHeader';
import { Pagination } from '@/components/ui/Pagination';
import { Skeleton } from '@/components/ui/skeleton';
import { PERMISSIONS } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { useCan } from '@/hooks/useCan';
import { useServiceScheduleMutations, useServiceSchedules } from '@/hooks/useServiceSchedules';
import type { ServiceSchedule } from '@/types/services';

/** How many placeholder rows to show while the first page loads. */
const LOADING_ROWS = 3;

/**
 * The church's weekly schedules: set one up once and its services are made for the coming weeks. Staff who may create
 * services can set up, change, turn off and turn on schedules. The page is in the URL.
 */
export function ServiceSchedulesPage() {
    const canEdit = useCan(PERMISSIONS.MEMBERS_EDIT);
    const [params, setParams] = useSearchParams();
    const page = Math.max(1, Number(params.get('page')) || 1);
    const schedules = useServiceSchedules(page, canEdit);
    const { update } = useServiceScheduleMutations();
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<ServiceSchedule | undefined>(undefined);

    if (!canEdit) {
        return (
            <div className="mx-auto flex max-w-3xl flex-col gap-3">
                <ErrorNotice message="Your role cannot manage weekly schedules." />
                <Link to={ROUTES.SERVICES} className="text-sm underline underline-offset-4">
                    Back to services
                </Link>
            </div>
        );
    }

    function toggle(schedule: ServiceSchedule) {
        update.mutate(
            { id: schedule.id, input: { is_active: !schedule.is_active } },
            {
                onSuccess: () =>
                    toast.success(schedule.is_active ? 'Schedule turned off. Services already made stay.' : 'Schedule turned on.'),
                onError: (error) => toast.error(error.message),
            },
        );
    }

    const newButton = (
        <Button size="lg" onClick={() => setCreating(true)}>
            New schedule
        </Button>
    );

    return (
        <div className="mx-auto flex max-w-3xl flex-col gap-4">
            <Link to={ROUTES.SERVICES} className="text-sm underline underline-offset-4">
                ← All services
            </Link>
            <PageHeader
                title="Weekly schedules"
                description="Set a service up once and it is made for you every week."
                actions={newButton}
            />

            {schedules.isPending && (
                <div aria-busy="true" className="flex flex-col gap-2">
                    {Array.from({ length: LOADING_ROWS }, (_, row) => (
                        <Skeleton key={row} className="h-[64px] w-full rounded-xl" />
                    ))}
                </div>
            )}

            {schedules.isError && <ErrorNotice message={schedules.error.message} onRetry={() => void schedules.refetch()} />}

            {schedules.data && schedules.data.data.length === 0 && (
                <EmptyState
                    icon={CalendarClock}
                    title="No weekly schedules yet"
                    description="Set up your Sunday service once, and you will not need to create it each week."
                    action={newButton}
                />
            )}

            {schedules.data && schedules.data.data.length > 0 && (
                <>
                    <ul className="flex flex-col gap-2" aria-live="polite">
                        {schedules.data.data.map((schedule) => (
                            <ServiceScheduleListItem
                                key={schedule.id}
                                schedule={schedule}
                                onEdit={setEditing}
                                onToggle={toggle}
                                busy={update.isPending && update.variables?.id === schedule.id}
                            />
                        ))}
                    </ul>
                    <Pagination
                        page={schedules.data.meta.current_page}
                        lastPage={schedules.data.meta.last_page}
                        onPageChange={(next) => setParams((current) => (current.set('page', String(next)), current))}
                        disabled={schedules.isPlaceholderData}
                    />
                </>
            )}

            <ServiceScheduleDialog open={creating} onOpenChange={setCreating} />
            <ServiceScheduleDialog
                open={editing !== undefined}
                onOpenChange={(open) => !open && setEditing(undefined)}
                schedule={editing}
            />
        </div>
    );
}
