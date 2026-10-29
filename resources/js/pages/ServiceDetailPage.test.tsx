import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PERMISSIONS, type Permission } from '@/constants/permissions';
import { ROUTE_PATTERNS } from '@/constants/routes';
import { ServiceDetailPage } from '@/pages/ServiceDetailPage';
import { ApiError } from '@/services/ApiError';
import { attendanceApi } from '@/services/api/attendance';
import { membersApi } from '@/services/api/members';
import { servicesApi } from '@/services/api/services';
import { attendanceRecord, memberSummary, pageOf, recorded, service, serviceAttendance, visitor } from '@/test/fixtures';
import { renderPage } from '@/test/render';
import { daysFromToday, today } from '@/utils/today';
import { toast } from 'sonner';

vi.mock('@/services/api/services');
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));
vi.mock('@/services/api/attendance');
vi.mock('@/services/api/members');

const EDITOR: Permission[] = [PERMISSIONS.MEMBERS_VIEW, PERMISSIONS.MEMBERS_EDIT];

function open(permissions: Permission[] = [], query = '') {
    return renderPage(<ServiceDetailPage />, { path: ROUTE_PATTERNS.SERVICE, url: `/services/service-1${query}`, permissions });
}

describe('ServiceDetailPage', () => {
    beforeEach(() => {
        vi.mocked(servicesApi.get).mockResolvedValue(service({ is_open: true }));
        vi.mocked(attendanceApi.forService).mockResolvedValue(serviceAttendance([attendanceRecord()]));
        vi.mocked(membersApi.list).mockResolvedValue(
            pageOf([memberSummary(), memberSummary({ id: 'member-2', full_name: 'Abena Asante', member_number: 'GRC-0002' })]),
        );
    });

    it('shows the service, its check-in code, the headcount and the latest check-ins', async () => {
        open();

        expect(await screen.findByRole('heading', { level: 1, name: 'Sunday Morning Service' })).toBeInTheDocument();
        expect(screen.getByText('Check-in is open')).toBeInTheDocument();
        expect(screen.getByText('GRA7F2')).toBeInTheDocument();
        expect(await screen.findByRole('region', { name: 'Headcount' })).toHaveTextContent('1 checked in');
        expect(screen.getByText('Emmanuel Mensah-Boateng')).toBeInTheDocument();
        expect(screen.getByText('Manual')).toBeInTheDocument();
    });

    it('closes check-in with one tap', async () => {
        vi.mocked(servicesApi.setCheckin).mockResolvedValue(service({ is_open: false }));
        const user = userEvent.setup();
        open(EDITOR);

        await user.click(await screen.findByRole('button', { name: 'Close check-in' }));

        await waitFor(() => expect(servicesApi.setCheckin).toHaveBeenCalledWith('service-1', false));
    });

    it('opens a closed service', async () => {
        vi.mocked(servicesApi.get).mockResolvedValue(service({ is_open: false, service_date: today() }));
        vi.mocked(servicesApi.setCheckin).mockResolvedValue(service({ is_open: true }));
        const user = userEvent.setup();
        open(EDITOR);

        await user.click(await screen.findByRole('button', { name: 'Open check-in' }));

        await waitFor(() => expect(servicesApi.setCheckin).toHaveBeenCalledWith('service-1', true));
    });

    it('hides the staff buttons from roles that cannot check people in', async () => {
        open([PERMISSIONS.MEMBERS_VIEW]);

        await screen.findByRole('heading', { level: 1, name: 'Sunday Morning Service' });
        expect(screen.queryByRole('button', { name: 'Close check-in' })).not.toBeInTheDocument();
        expect(screen.queryByLabelText('Find a member')).not.toBeInTheDocument();
        expect(screen.getByText(/Ask the church admin or the pastor/)).toBeInTheDocument();
    });

    it('finds members, ticks several and marks them present together', async () => {
        vi.mocked(attendanceApi.record).mockResolvedValue(recorded({ recorded: 2 }));
        const user = userEvent.setup();
        open(EDITOR);

        await user.type(await screen.findByLabelText('Find a member'), 'a');
        await user.click(screen.getByRole('button', { name: 'Search' }));
        await user.click(await screen.findByLabelText(/Emmanuel Mensah-Boateng/));
        await user.click(screen.getByLabelText(/Abena Asante/));
        await user.click(screen.getByRole('button', { name: 'Mark 2 present' }));

        await waitFor(() => expect(attendanceApi.record).toHaveBeenCalledWith('service-1', ['member-1', 'member-2']));
        await waitFor(() => expect(screen.queryByRole('button', { name: /Mark \d present/ })).not.toBeInTheDocument());
    });

    it('adds a visitor with only a name', async () => {
        vi.mocked(attendanceApi.captureVisitor).mockResolvedValue(visitor());
        const user = userEvent.setup();
        open(EDITOR);

        await user.click(await screen.findByRole('button', { name: 'Add a visitor' }));
        await user.type(screen.getByLabelText(/^Name/), 'Yaw Boateng');
        await user.click(screen.getByRole('button', { name: 'Add visitor' }));

        await waitFor(() =>
            expect(attendanceApi.captureVisitor).toHaveBeenCalledWith('service-1', {
                name: 'Yaw Boateng',
                phone: undefined,
                how_heard: undefined,
            }),
        );
    });

    it('opens on the Attendance tab named in the address', async () => {
        vi.mocked(attendanceApi.forService).mockResolvedValue(
            serviceAttendance([
                attendanceRecord(),
                attendanceRecord({ id: 'record-2', member: null, visitor_name: 'Yaw Boateng', method: 'manual' }),
            ]),
        );
        open([], '?tab=attendance');

        expect(await screen.findByText('Yaw Boateng')).toBeInTheDocument();
        expect(screen.getByText('Visitor')).toBeInTheDocument();
    });

    it('says nobody has checked in yet for an empty service', async () => {
        vi.mocked(attendanceApi.forService).mockResolvedValue(serviceAttendance([]));
        open();

        expect(await screen.findByText('Nobody has checked in yet')).toBeInTheDocument();
    });

    it('says plainly when the service is not found', async () => {
        vi.mocked(servicesApi.get).mockRejectedValue(new ApiError('Not found', 404));
        open();

        expect(await screen.findByRole('alert')).toHaveTextContent('This service was not found.');
        expect(screen.getByRole('link', { name: 'Back to services' })).toBeInTheDocument();
    });

    describe('opening check-in only on the day', () => {
        it('cannot be opened for a service on another day, and says why', async () => {
            vi.mocked(servicesApi.get).mockResolvedValue(service({ is_open: false, service_date: daysFromToday(7) }));
            open(EDITOR);

            expect(await screen.findByRole('button', { name: 'Open check-in' })).toBeDisabled();
            expect(screen.getByText('Check-in can be opened on the day of the service.')).toBeInTheDocument();
        });

        it('cannot be opened for a service that was yesterday either', async () => {
            vi.mocked(servicesApi.get).mockResolvedValue(service({ is_open: false, service_date: daysFromToday(-1) }));
            open(EDITOR);

            expect(await screen.findByRole('button', { name: 'Open check-in' })).toBeDisabled();
        });

        it('can always be closed, even on another day', async () => {
            vi.mocked(servicesApi.get).mockResolvedValue(service({ is_open: true, service_date: daysFromToday(-1) }));
            open(EDITOR);

            expect(await screen.findByRole('button', { name: 'Close check-in' })).toBeEnabled();
            expect(screen.queryByText('Check-in can be opened on the day of the service.')).not.toBeInTheDocument();
        });

        it('is open to use on the day of the service', async () => {
            vi.mocked(servicesApi.get).mockResolvedValue(service({ is_open: false, service_date: today() }));
            open(EDITOR);

            expect(await screen.findByRole('button', { name: 'Open check-in' })).toBeEnabled();
            expect(screen.queryByText('Check-in can be opened on the day of the service.')).not.toBeInTheDocument();
        });

        it('shows nothing about opening to someone who cannot change services', async () => {
            vi.mocked(servicesApi.get).mockResolvedValue(service({ is_open: false, service_date: daysFromToday(7) }));
            open([PERMISSIONS.MEMBERS_VIEW]);
            await screen.findByRole('heading', { level: 1, name: 'Sunday Morning Service' });

            expect(screen.queryByText('Check-in can be opened on the day of the service.')).not.toBeInTheDocument();
        });
    });

    describe('cancelling the service', () => {
        beforeEach(() => {
            vi.mocked(servicesApi.get).mockResolvedValue(service({ is_open: false, attendance_count: 0 }));
            vi.mocked(servicesApi.cancel).mockResolvedValue(undefined);
        });

        it('asks first, then cancels and goes back to the list', async () => {
            const user = userEvent.setup();
            const { router } = open(EDITOR);

            await user.click(await screen.findByRole('button', { name: 'Cancel service' }));
            expect(await screen.findByText('Cancel this service?')).toBeInTheDocument();
            expect(servicesApi.cancel).not.toHaveBeenCalled();
            const dialog = screen.getByRole('alertdialog');
            await user.click(within(dialog).getByRole('button', { name: 'Cancel service' }));

            await waitFor(() => expect(servicesApi.cancel).toHaveBeenCalledWith('service-1'));
            await waitFor(() => expect(router.state.location.pathname).toBe('/services'));
        });

        it('stays on the page and shows the reason when the server refuses', async () => {
            vi.mocked(servicesApi.cancel).mockRejectedValue(
                new ApiError('This service already has attendance or gifts recorded, so it cannot be cancelled. Close it instead.', 409),
            );
            const user = userEvent.setup();
            const { router } = open(EDITOR);

            await user.click(await screen.findByRole('button', { name: 'Cancel service' }));
            await user.click(within(screen.getByRole('alertdialog')).getByRole('button', { name: 'Cancel service' }));

            await waitFor(() => expect(toast.error).toHaveBeenCalledWith(expect.stringContaining('cannot be cancelled. Close it instead')));
            expect(router.state.location.pathname).toBe('/services/service-1');
        });

        it('cannot be tried while check-in is open or when people are checked in', async () => {
            vi.mocked(servicesApi.get).mockResolvedValue(service({ is_open: true }));
            open(EDITOR);
            expect(await screen.findByRole('button', { name: 'Cancel service' })).toBeDisabled();
        });

        it('is not offered to someone who cannot change services', async () => {
            open([PERMISSIONS.MEMBERS_VIEW]);
            await screen.findByRole('heading', { level: 1, name: 'Sunday Morning Service' });

            expect(screen.queryByRole('button', { name: 'Cancel service' })).not.toBeInTheDocument();
        });

        it('says when a weekly schedule made the service', async () => {
            vi.mocked(servicesApi.get).mockResolvedValue(service({ schedule_id: 'schedule-1' }));
            open(EDITOR);

            expect(await screen.findByText(/Made by a weekly schedule/)).toBeInTheDocument();
        });
    });
});
