import { useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router';
import { AttendanceList } from '@/components/Services/AttendanceList';
import { AttendanceSummaryCards } from '@/components/Services/AttendanceSummaryCards';
import { MemberCheckInPicker } from '@/components/Services/MemberCheckInPicker';
import { VisitorDialog } from '@/components/Services/VisitorDialog';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { PageHeader } from '@/components/ui/PageHeader';
import { Pagination } from '@/components/ui/Pagination';
import { Skeleton } from '@/components/ui/skeleton';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { PERMISSIONS } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { useCan } from '@/hooks/useCan';
import { useServiceAttendance } from '@/hooks/useServiceAttendance';
import { useService, useServiceMutations } from '@/hooks/useServices';
import { useUrlTab } from '@/hooks/useUrlTab';
import { ApiError } from '@/services/ApiError';
import { formatDate } from '@/utils/formatDate';
import { today } from '@/utils/today';
import { toast } from 'sonner';

const TABS = ['check-in', 'attendance'] as const;

/**
 * One service: open or close check-in, watch the headcount update every ten seconds while it is open, mark people
 * present, add visitors, and read the attendance. The tab and the attendance page are in the URL.
 */
export function ServiceDetailPage() {
    const { id = '' } = useParams();
    const service = useService(id);
    const canEdit = useCan(PERMISSIONS.MEMBERS_EDIT);
    const { setCheckin, cancel } = useServiceMutations();
    const navigate = useNavigate();
    const [cancelling, setCancelling] = useState(false);
    const [tab, selectTab] = useUrlTab(TABS);
    const [params, setParams] = useSearchParams();
    const page = Math.max(1, Number(params.get('page')) || 1);
    const [addingVisitor, setAddingVisitor] = useState(false);
    const attendance = useServiceAttendance(id, page, service.data?.is_open ?? false);

    if (service.isPending) {
        return <Skeleton aria-busy="true" className="mx-auto h-64 max-w-3xl rounded-xl" />;
    }

    if (service.isError) {
        const missing = service.error instanceof ApiError && (service.error.status === 404 || service.error.status === 403);

        return (
            <div className="mx-auto flex max-w-3xl flex-col gap-3">
                <ErrorNotice
                    message={missing ? 'This service was not found.' : service.error.message}
                    onRetry={missing ? undefined : () => void service.refetch()}
                />
                <Link to={ROUTES.SERVICES} className="text-sm underline underline-offset-4">
                    Back to services
                </Link>
            </div>
        );
    }

    const record = service.data;

    function toggleCheckin() {
        setCheckin.mutate(
            { id: record.id, isOpen: !record.is_open },
            {
                onSuccess: () => toast.success(record.is_open ? 'Check-in closed.' : 'Check-in is open.'),
                onError: (error) => toast.error(error.message),
            },
        );
    }

    // The server decides in the end (a gift also stops a cancel); this only saves a click for the common cases.
    const cannotCancel = record.is_open || (record.attendance_count ?? 0) > 0;
    // Check-in can only be opened on the day of the service (D-052); closing is always possible.
    const cannotOpen = !record.is_open && record.service_date !== today();

    function cancelService() {
        cancel.mutate(record.id, {
            onSuccess: () => {
                toast.success('Service cancelled.');
                void navigate(ROUTES.SERVICES);
            },
            onError: (error) => {
                setCancelling(false);
                toast.error(error.message);
            },
        });
    }

    const actions = canEdit ? (
        <>
            {record.is_open && (
                <Button asChild size="lg" variant="outline">
                    <Link to={ROUTES.SERVICE_QR(record.id)}>Show QR code</Link>
                </Button>
            )}
            <Button
                size="lg"
                variant={record.is_open ? 'outline' : 'default'}
                onClick={toggleCheckin}
                disabled={setCheckin.isPending || cannotOpen}
                aria-describedby={cannotOpen ? 'open-hint' : undefined}
            >
                {record.is_open ? 'Close check-in' : 'Open check-in'}
            </Button>
            <Button size="lg" variant="outline" onClick={() => setCancelling(true)} disabled={cannotCancel} aria-describedby="cancel-hint">
                Cancel service
            </Button>
        </>
    ) : undefined;

    return (
        <div className="mx-auto flex max-w-3xl flex-col gap-4">
            <Link to={ROUTES.SERVICES} className="text-sm underline underline-offset-4">
                ← All services
            </Link>
            <PageHeader
                title={record.name}
                description={`${formatDate(record.service_date)}${record.starts_at ? ` · ${record.starts_at}` : ''} · ${record.type_label}`}
                actions={actions}
            />
            {canEdit && cannotOpen && (
                <p id="open-hint" className="text-sm text-muted-foreground">
                    Check-in can be opened on the day of the service.
                </p>
            )}
            {canEdit && (
                <p id="cancel-hint" className="text-sm text-muted-foreground">
                    {record.schedule_id ? 'Made by a weekly schedule. ' : ''}A service with check-ins, or with check-in still open, cannot
                    be cancelled.
                </p>
            )}
            <p className="flex flex-wrap items-center gap-2 text-sm">
                <StatusBadge
                    label={record.is_open ? 'Check-in is open' : 'Check-in is closed'}
                    tone={record.is_open ? 'success' : 'neutral'}
                />
                {record.checkin_code && (
                    <span>
                        Check-in code: <strong>{record.checkin_code}</strong>
                    </span>
                )}
            </p>

            {attendance.isError && <ErrorNotice message={attendance.error.message} onRetry={() => void attendance.refetch()} />}
            {attendance.data && <AttendanceSummaryCards summary={attendance.data.summary} />}

            <Tabs value={tab} onValueChange={selectTab}>
                <TabsList>
                    <TabsTrigger value="check-in">Check-in</TabsTrigger>
                    <TabsTrigger value="attendance">Attendance</TabsTrigger>
                </TabsList>
                <TabsContent value="check-in" className="flex flex-col gap-4">
                    {canEdit ? (
                        <>
                            <MemberCheckInPicker serviceId={record.id} />
                            <Button type="button" variant="outline" onClick={() => setAddingVisitor(true)}>
                                Add a visitor
                            </Button>
                            <VisitorDialog serviceId={record.id} open={addingVisitor} onOpenChange={setAddingVisitor} />
                        </>
                    ) : (
                        <p className="text-sm text-muted-foreground">Ask the church admin or the pastor to check people in.</p>
                    )}
                    <h2 className="font-medium">Latest check-ins</h2>
                    <AttendanceList records={(attendance.data?.data ?? []).slice(0, 10)} />
                </TabsContent>
                <TabsContent value="attendance" className="flex flex-col gap-4">
                    <AttendanceList records={attendance.data?.data ?? []} />
                    {attendance.data && (
                        <Pagination
                            page={attendance.data.meta.current_page}
                            lastPage={attendance.data.meta.last_page}
                            onPageChange={(next) => setParams((current) => (current.set('page', String(next)), current))}
                            disabled={attendance.isPlaceholderData}
                        />
                    )}
                </TabsContent>
            </Tabs>
            <ConfirmDialog
                open={cancelling}
                onOpenChange={setCancelling}
                title="Cancel this service?"
                description="It is taken off the calendar and will not be made again by a weekly schedule. You can still create a service for this day by hand."
                confirmLabel="Cancel service"
                onConfirm={cancelService}
                pending={cancel.isPending}
            />
        </div>
    );
}
