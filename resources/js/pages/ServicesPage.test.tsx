import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PERMISSIONS, type Permission } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { ServicesPage } from '@/pages/ServicesPage';
import { ApiError } from '@/services/ApiError';
import { servicesApi } from '@/services/api/services';
import { pageOf, service } from '@/test/fixtures';
import { renderPage } from '@/test/render';
import { daysFromToday } from '@/utils/today';

/** A date a week from today, as the API writes it and as the date input takes it (year, month, day). */
const NEXT_WEEK = daysFromToday(7);

vi.mock('@/services/api/services');

function open(url: string = ROUTES.SERVICES, permissions: Permission[] = []) {
    return renderPage(<ServicesPage />, { path: ROUTES.SERVICES, url, permissions });
}

describe('ServicesPage', () => {
    beforeEach(() => {
        vi.mocked(servicesApi.list).mockResolvedValue(
            pageOf([
                service({ attendance_count: 12, is_open: true }),
                service({ id: 'service-2', name: 'Midweek Prayer', type: 'midweek', type_label: 'Midweek' }),
            ]),
        );
    });

    it('lists the services with whether check-in is open and how many are in', async () => {
        open();

        const first = await screen.findByRole('link', { name: /Sunday Morning Service/ });
        expect(first).toHaveTextContent('Open');
        expect(first).toHaveTextContent('12 checked in');
        expect(screen.getByRole('link', { name: /Midweek Prayer/ })).toHaveTextContent('Closed');
        expect(servicesApi.list).toHaveBeenCalledWith({ type: '', page: 1 });
    });

    it('filters by type and keeps the type in the address', async () => {
        const user = userEvent.setup();
        const { router } = open();
        await screen.findByRole('link', { name: /Sunday/ });

        await user.selectOptions(screen.getByLabelText('Type'), 'midweek');

        await waitFor(() => expect(servicesApi.list).toHaveBeenLastCalledWith({ type: 'midweek', page: 1 }));
        expect(router.state.location.search).toBe('?type=midweek');
    });

    it('ignores an unknown type in the address', async () => {
        open('/services?type=party');

        await screen.findByRole('link', { name: /Sunday/ });
        expect(servicesApi.list).toHaveBeenCalledWith({ type: '', page: 1 });
    });

    it('shows an empty state with the next step for staff who may create', async () => {
        vi.mocked(servicesApi.list).mockResolvedValue(pageOf([]));
        open(ROUTES.SERVICES, [PERMISSIONS.MEMBERS_EDIT]);

        expect(await screen.findByText('No services found')).toBeInTheDocument();
        expect(screen.getAllByRole('button', { name: 'New service' }).length).toBeGreaterThan(0);
    });

    it('hides the New service button from staff who may not create', async () => {
        open();

        await screen.findByRole('link', { name: /Sunday/ });
        expect(screen.queryByRole('button', { name: 'New service' })).not.toBeInTheDocument();
    });

    it('creates a service and opens its page', async () => {
        vi.mocked(servicesApi.create).mockResolvedValue(service({ id: 'new-service' }));
        const user = userEvent.setup();
        const { router } = open(ROUTES.SERVICES, [PERMISSIONS.MEMBERS_EDIT]);
        await screen.findByRole('link', { name: /Sunday/ });

        await user.click(screen.getByRole('button', { name: 'New service' }));
        await user.type(screen.getByLabelText(/^Name/), 'Easter Service');
        await user.type(screen.getByLabelText(/^Date/), NEXT_WEEK);
        await user.click(screen.getByRole('button', { name: 'Create service' }));

        await waitFor(() =>
            expect(servicesApi.create).toHaveBeenCalledWith(
                expect.objectContaining({ name: 'Easter Service', type: 'sunday', service_date: NEXT_WEEK }),
            ),
        );
        await waitFor(() => expect(router.state.location.pathname).toBe('/services/new-service'));
    });

    it('checks the form before sending it', async () => {
        const user = userEvent.setup();
        open(ROUTES.SERVICES, [PERMISSIONS.MEMBERS_EDIT]);
        await screen.findByRole('link', { name: /Sunday/ });

        await user.click(screen.getByRole('button', { name: 'New service' }));
        await user.click(screen.getByRole('button', { name: 'Create service' }));

        expect(await screen.findByText('Enter the name of the service')).toBeInTheDocument();
        expect(screen.getByText('Enter the date')).toBeInTheDocument();
        expect(servicesApi.create).not.toHaveBeenCalled();
    });

    it("uses the browser's own date input, which cannot offer a date the server would refuse", async () => {
        const user = userEvent.setup();
        open(ROUTES.SERVICES, [PERMISSIONS.MEMBERS_EDIT]);
        await screen.findByRole('link', { name: /Sunday/ });
        await user.click(screen.getByRole('button', { name: 'New service' }));

        const box = screen.getByLabelText(/^Date/);
        await user.type(box, '2026-12-25');

        expect(box).toHaveAttribute('type', 'date');
        expect(box).toHaveValue('2026-12-25');
        expect(box).toHaveAttribute('min', daysFromToday(-30));
        expect(box).toHaveAttribute('max', daysFromToday(366));
    });

    it('refuses a date that is too old or too far ahead, before sending', async () => {
        const user = userEvent.setup();
        open(ROUTES.SERVICES, [PERMISSIONS.MEMBERS_EDIT]);
        await screen.findByRole('link', { name: /Sunday/ });
        await user.click(screen.getByRole('button', { name: 'New service' }));
        await user.type(screen.getByLabelText(/^Name/), 'X');

        await user.type(screen.getByLabelText(/^Date/), '2020-01-01');
        await user.click(screen.getByRole('button', { name: 'Create service' }));
        expect(await screen.findByText('A service cannot be dated more than 30 days in the past')).toBeInTheDocument();

        await user.clear(screen.getByLabelText(/^Date/));
        await user.type(screen.getByLabelText(/^Date/), '2099-01-01');
        await user.click(screen.getByRole('button', { name: 'Create service' }));
        expect(await screen.findByText('A service cannot be dated more than 366 days ahead')).toBeInTheDocument();
        expect(servicesApi.create).not.toHaveBeenCalled();
    });

    it('shows a server message beside its box', async () => {
        vi.mocked(servicesApi.create).mockRejectedValue(new ApiError('Invalid', 422, { ends_at: ['The end must be after the start.'] }));
        const user = userEvent.setup();
        open(ROUTES.SERVICES, [PERMISSIONS.MEMBERS_EDIT]);
        await screen.findByRole('link', { name: /Sunday/ });

        await user.click(screen.getByRole('button', { name: 'New service' }));
        await user.type(screen.getByLabelText(/^Name/), 'X');
        await user.type(screen.getByLabelText(/^Date/), NEXT_WEEK);
        await user.click(screen.getByRole('button', { name: 'Create service' }));

        expect(await screen.findByText('The end must be after the start.')).toBeInTheDocument();
    });

    it('says what went wrong and offers to try again', async () => {
        vi.mocked(servicesApi.list).mockRejectedValue(new ApiError('The server is busy.', 500));
        open();

        expect(await screen.findByRole('alert')).toHaveTextContent('The server is busy.');
        expect(screen.getByRole('button', { name: 'Try again' })).toBeInTheDocument();
    });

    it('offers the weekly schedules to staff who may create services, and no one else', async () => {
        const first = open(ROUTES.SERVICES, [PERMISSIONS.MEMBERS_EDIT]);
        await screen.findByRole('link', { name: /Sunday Morning Service/ });
        expect(screen.getByRole('link', { name: 'Weekly schedules' })).toHaveAttribute('href', '/services/schedules');
        first.unmount();

        open();
        await screen.findByRole('link', { name: /Sunday Morning Service/ });
        expect(screen.queryByRole('link', { name: 'Weekly schedules' })).not.toBeInTheDocument();
    });

    it('marks a service that a weekly schedule made', async () => {
        vi.mocked(servicesApi.list).mockResolvedValue(
            pageOf([service({ schedule_id: 'schedule-1' }), service({ id: 'service-2', name: 'By Hand' })]),
        );
        open();

        expect(await screen.findByRole('link', { name: /Sunday Morning Service/ })).toHaveTextContent('Weekly');
        expect(screen.getByRole('link', { name: /By Hand/ })).not.toHaveTextContent('Weekly');
    });
});
