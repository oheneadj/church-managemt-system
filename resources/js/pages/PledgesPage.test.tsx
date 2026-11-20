import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PERMISSIONS, type Permission } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { PledgesPage } from '@/pages/PledgesPage';
import { ApiError } from '@/services/ApiError';
import { fundTypesApi } from '@/services/api/fundTypes';
import { pledgesApi } from '@/services/api/pledges';
import { fundType, pageOf, pledge } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/services/api/pledges');
vi.mock('@/services/api/fundTypes');
vi.mock('@/services/api/members');

const FINANCE: Permission[] = [PERMISSIONS.GIVING_VIEW, PERMISSIONS.GIVING_RECORD, PERMISSIONS.GIVING_EDIT];

function open(permissions: Permission[] = FINANCE, url: string = ROUTES.PLEDGES) {
    return renderPage(<PledgesPage />, { path: ROUTES.PLEDGES, url, permissions });
}

describe('PledgesPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.mocked(fundTypesApi.active).mockResolvedValue([
            fundType({ id: 'fund-1', name: 'Building Fund' }),
            fundType({ id: 'fund-2', name: 'Missions' }),
        ]);
        vi.mocked(pledgesApi.list).mockResolvedValue(
            pageOf([
                pledge(),
                pledge({ id: 'pledge-2', description: 'Missions pledge', status: 'lapsed', status_label: 'Lapsed', progress_percent: 0 }),
            ]),
        );
    });

    it('lists each pledge with its progress and status', async () => {
        open();

        const rows = await screen.findAllByRole('listitem');
        expect(rows[0]).toHaveTextContent('Building Fund 2026 pledge');
        expect(rows[0]).toHaveTextContent('GHS 700.00 outstanding');
        expect(rows[1]).toHaveTextContent('Lapsed');
        expect(screen.getByText('2 pledges found')).toBeInTheDocument();
    });

    it('asks the server for the filters in the address, ignoring a status that does not exist', async () => {
        open(FINANCE, `${ROUTES.PLEDGES}?status=partial&fund=fund-2&page=2`);

        await waitFor(() => expect(pledgesApi.list).toHaveBeenCalledWith({ status: 'partial', fund: 'fund-2', page: 2 }));
    });

    it('ignores a status value that is not valid', async () => {
        open(FINANCE, `${ROUTES.PLEDGES}?status=banana`);

        await waitFor(() => expect(pledgesApi.list).toHaveBeenCalledWith({ status: '', fund: '', page: 1 }));
    });

    it('filters by status and returns to page 1', async () => {
        const user = userEvent.setup();
        const { router } = open(FINANCE, `${ROUTES.PLEDGES}?page=3`);

        await user.selectOptions(await screen.findByLabelText('Status'), 'lapsed');

        await waitFor(() => expect(router.state.location.search).toBe('?status=lapsed'));
    });

    it('offers New pledge and Change only to the staff who may use them', async () => {
        open([PERMISSIONS.GIVING_VIEW]);

        await screen.findAllByRole('listitem');
        expect(screen.queryByRole('button', { name: 'New pledge' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Change' })).not.toBeInTheDocument();
    });

    it('opens the new pledge form', async () => {
        const user = userEvent.setup();
        open();

        await user.click(await screen.findByRole('button', { name: 'New pledge' }));

        expect(await screen.findByRole('dialog', { name: 'New pledge' })).toBeInTheDocument();
    });

    it('opens the change form for the pledge that was chosen', async () => {
        const user = userEvent.setup();
        open();

        const row = (await screen.findAllByRole('listitem'))[1] as HTMLElement;
        await user.click(within(row).getByRole('button', { name: 'Change' }));

        const dialog = await screen.findByRole('dialog', { name: 'Change pledge' });
        expect(within(dialog).getByLabelText(/What it is for/)).toHaveValue('Missions pledge');
    });

    it('gives the form its own fields, apart from the filters behind it', async () => {
        const user = userEvent.setup();
        open();

        await user.click(await screen.findByRole('button', { name: 'New pledge' }));
        const dialog = await screen.findByRole('dialog', { name: 'New pledge' });

        expect(within(dialog).getByRole('combobox', { name: /^Fund/ })).toBeInTheDocument();
        const ids = [...document.querySelectorAll('[id]')].map((element) => element.id);
        expect(new Set(ids).size).toBe(ids.length);
    });

    it('says plainly when there are no pledges', async () => {
        vi.mocked(pledgesApi.list).mockResolvedValue(pageOf([]));
        open();

        expect(await screen.findByText('No pledges found')).toBeInTheDocument();
    });

    it('shows an error with a way to try again', async () => {
        vi.mocked(pledgesApi.list).mockRejectedValue(new ApiError('Something went wrong. Please try again.', 500));
        open();

        expect(await screen.findByText('Something went wrong. Please try again.')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /try again/i })).toBeInTheDocument();
    });
});
