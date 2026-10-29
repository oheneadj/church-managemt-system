import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PERMISSIONS, type Permission } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { ServiceSchedulesPage } from '@/pages/ServiceSchedulesPage';
import { ApiError } from '@/services/ApiError';
import { serviceSchedulesApi } from '@/services/api/serviceSchedules';
import { pageOf, serviceSchedule } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/services/api/serviceSchedules');

const EDITOR: Permission[] = [PERMISSIONS.MEMBERS_VIEW, PERMISSIONS.MEMBERS_EDIT];

function open(permissions: Permission[] = EDITOR) {
    return renderPage(<ServiceSchedulesPage />, { path: ROUTES.SERVICE_SCHEDULES, url: ROUTES.SERVICE_SCHEDULES, permissions });
}

describe('ServiceSchedulesPage', () => {
    beforeEach(() => {
        vi.mocked(serviceSchedulesApi.list).mockResolvedValue(
            pageOf([
                serviceSchedule(),
                serviceSchedule({
                    id: 'schedule-2',
                    name: 'Midweek Prayer',
                    type: 'midweek',
                    type_label: 'Midweek',
                    weekday: 'wednesday',
                    weekday_label: 'Wednesday',
                    starts_at: '18:00',
                    ends_at: null,
                    is_active: false,
                }),
            ]),
        );
        vi.mocked(serviceSchedulesApi.create).mockResolvedValue(serviceSchedule({ id: 'new' }));
        vi.mocked(serviceSchedulesApi.update).mockResolvedValue(serviceSchedule());
    });

    it('lists each schedule with its day, times and whether it is on', async () => {
        open();

        const first = (await screen.findByText('Sunday Morning Service')).closest('li') as HTMLElement;
        expect(first).toHaveTextContent('Every Sunday · 09:00 to 11:30 · Sunday');
        expect(within(first).getByText('On')).toBeInTheDocument();
        const second = screen.getByText('Midweek Prayer').closest('li') as HTMLElement;
        expect(second).toHaveTextContent('Every Wednesday · 18:00 · Midweek');
        expect(within(second).getByText('Off')).toBeInTheDocument();
    });

    it('says plainly when nobody may manage schedules', () => {
        open([PERMISSIONS.MEMBERS_VIEW]);

        expect(screen.getByRole('alert')).toHaveTextContent('Your role cannot manage weekly schedules.');
        expect(serviceSchedulesApi.list).not.toHaveBeenCalled();
    });

    it('invites the first schedule when there is none', async () => {
        vi.mocked(serviceSchedulesApi.list).mockResolvedValue(pageOf([]));
        open();

        expect(await screen.findByText('No weekly schedules yet')).toBeInTheDocument();
    });

    it('says what went wrong and offers to try again', async () => {
        vi.mocked(serviceSchedulesApi.list).mockRejectedValue(new ApiError('The server is busy.', 500));
        open();

        expect(await screen.findByRole('alert')).toHaveTextContent('The server is busy.');
        expect(screen.getByRole('button', { name: 'Try again' })).toBeInTheDocument();
    });

    it('sets a schedule up, sending one key, and tells the person the weeks are on the calendar', async () => {
        const user = userEvent.setup();
        open();
        await screen.findByText('Sunday Morning Service');

        await user.click(screen.getAllByRole('button', { name: 'New schedule' })[0] as HTMLElement);
        await user.type(screen.getByLabelText(/^Name/), 'Evening Service');
        await user.selectOptions(screen.getByLabelText(/^Every/), 'friday');
        await user.type(screen.getByLabelText(/^Starts/), '18:00');
        await user.click(screen.getByRole('button', { name: 'Set up schedule' }));

        await waitFor(() => expect(serviceSchedulesApi.create).toHaveBeenCalledOnce());
        const [input, key] = vi.mocked(serviceSchedulesApi.create).mock.calls[0] ?? [];
        expect(input).toEqual({ name: 'Evening Service', type: 'sunday', weekday: 'friday', starts_at: '18:00', ends_at: null });
        expect(key).toMatch(/^[A-Za-z0-9_-]{8,100}$/);
    });

    it('checks the form before sending it', async () => {
        const user = userEvent.setup();
        open();
        await screen.findByText('Sunday Morning Service');

        await user.click(screen.getAllByRole('button', { name: 'New schedule' })[0] as HTMLElement);
        await user.click(screen.getByRole('button', { name: 'Set up schedule' }));

        expect(await screen.findByText('Enter the name of the service')).toBeInTheDocument();
        expect(serviceSchedulesApi.create).not.toHaveBeenCalled();
    });

    it('shows a server message beside its box', async () => {
        vi.mocked(serviceSchedulesApi.create).mockRejectedValue(
            new ApiError('Invalid', 422, { ends_at: ['The end must be after the start.'] }),
        );
        const user = userEvent.setup();
        open();
        await screen.findByText('Sunday Morning Service');

        await user.click(screen.getAllByRole('button', { name: 'New schedule' })[0] as HTMLElement);
        await user.type(screen.getByLabelText(/^Name/), 'X');
        await user.click(screen.getByRole('button', { name: 'Set up schedule' }));

        expect(await screen.findByText('The end must be after the start.')).toBeInTheDocument();
    });

    it('changes a schedule from its current values', async () => {
        const user = userEvent.setup();
        open();
        const row = (await screen.findByText('Sunday Morning Service')).closest('li') as HTMLElement;

        await user.click(within(row).getByRole('button', { name: 'Change' }));
        expect(screen.getByLabelText(/^Name/)).toHaveValue('Sunday Morning Service');
        expect(screen.getByLabelText(/^Starts/)).toHaveValue('09:00');
        await user.clear(screen.getByLabelText(/^Name/));
        await user.type(screen.getByLabelText(/^Name/), 'Worship Service');
        await user.click(screen.getByRole('button', { name: 'Save changes' }));

        await waitFor(() =>
            expect(serviceSchedulesApi.update).toHaveBeenCalledWith(
                'schedule-1',
                expect.objectContaining({ name: 'Worship Service', weekday: 'sunday', starts_at: '09:00', ends_at: '11:30' }),
            ),
        );
    });

    it('turns a schedule off and another on with one tap', async () => {
        const user = userEvent.setup();
        open();
        const first = (await screen.findByText('Sunday Morning Service')).closest('li') as HTMLElement;
        const second = screen.getByText('Midweek Prayer').closest('li') as HTMLElement;

        await user.click(within(first).getByRole('button', { name: 'Turn off' }));
        await waitFor(() => expect(serviceSchedulesApi.update).toHaveBeenCalledWith('schedule-1', { is_active: false }));

        await user.click(within(second).getByRole('button', { name: 'Turn on' }));
        await waitFor(() => expect(serviceSchedulesApi.update).toHaveBeenCalledWith('schedule-2', { is_active: true }));
    });
});
