import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { toast } from 'sonner';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PERMISSIONS } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { FundTypesPage } from '@/pages/FundTypesPage';
import { ApiError } from '@/services/ApiError';
import { fundTypesApi } from '@/services/api/fundTypes';
import { fundType } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/services/api/fundTypes');
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));

const tithe = fundType({ id: 'fund-1', name: 'Tithe', description: 'One tenth of income.', sort_order: 0 });
const missions = fundType({ id: 'fund-2', name: 'Missions', description: null, sort_order: 5 });
const old = fundType({ id: 'fund-3', name: 'Old Appeal', description: null, sort_order: 9, is_active: false });

function open() {
    return renderPage(<FundTypesPage />, { path: ROUTES.FUNDS, url: ROUTES.FUNDS, permissions: [PERMISSIONS.FUND_TYPES_MANAGE] });
}

/** The list item for a fund, found by its name. */
function row(name: string): HTMLElement {
    return screen.getByText(name).closest('li') as HTMLElement;
}

describe('FundTypesPage', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        vi.mocked(fundTypesApi.all).mockResolvedValue([tithe, missions, old]);
    });

    it('lists every fund with what it is for, and says which are in use and which are switched off', async () => {
        open();

        await screen.findByText('Tithe');
        expect(within(row('Tithe')).getByText('One tenth of income.')).toBeInTheDocument();
        expect(within(row('Tithe')).getByText('In use')).toBeInTheDocument();
        expect(within(row('Old Appeal')).getByText('Switched off')).toBeInTheDocument();
        expect(within(row('Old Appeal')).getByRole('button', { name: 'Switch on' })).toBeInTheDocument();
        expect(within(row('Missions')).getByRole('button', { name: 'Switch off' })).toBeInTheDocument();
    });

    it('adds a fund at the end of the list and says it is added', async () => {
        vi.mocked(fundTypesApi.create).mockResolvedValue(fundType({ id: 'fund-4', name: 'Harvest' }));
        const user = userEvent.setup();
        open();
        await screen.findByText('Tithe');

        await user.click(screen.getByRole('button', { name: 'Add a fund' }));
        const dialog = await screen.findByRole('dialog');
        expect(within(dialog).getByLabelText('Place in the list')).toHaveValue(10);
        await user.type(within(dialog).getByLabelText(/^Name/), 'Harvest');
        await user.click(within(dialog).getByRole('button', { name: 'Add fund' }));

        await waitFor(() => expect(fundTypesApi.create).toHaveBeenCalledWith({ name: 'Harvest', description: null, sort_order: 10 }));
        expect(toast.success).toHaveBeenCalledWith('Harvest is added.');
        await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
        // The list is asked for again, so the new fund appears (and the pickers on the gift forms see it too).
        await waitFor(() => expect(fundTypesApi.all).toHaveBeenCalledTimes(2));
    });

    it('will not add a fund without a name, and sends nothing', async () => {
        const user = userEvent.setup();
        open();
        await screen.findByText('Tithe');

        await user.click(screen.getByRole('button', { name: 'Add a fund' }));
        await user.click(await screen.findByRole('button', { name: 'Add fund' }));

        expect(await screen.findByText('Enter the name of the fund')).toBeInTheDocument();
        expect(fundTypesApi.create).not.toHaveBeenCalled();
    });

    it('shows beside the box that a name is already taken, and stays open', async () => {
        vi.mocked(fundTypesApi.create).mockRejectedValue(new ApiError('Invalid', 422, { name: ['A fund with this name already exists.'] }));
        const user = userEvent.setup();
        open();
        await screen.findByText('Tithe');

        await user.click(screen.getByRole('button', { name: 'Add a fund' }));
        await user.type(await screen.findByLabelText(/^Name/), 'TITHE');
        await user.click(screen.getByRole('button', { name: 'Add fund' }));

        expect(await screen.findByText('A fund with this name already exists.')).toBeInTheDocument();
        expect(screen.getByRole('dialog')).toBeInTheDocument();
    });

    it('changes a fund from what it is now, and clears a description that was emptied', async () => {
        vi.mocked(fundTypesApi.update).mockResolvedValue(tithe);
        const user = userEvent.setup();
        open();
        await screen.findByText('Tithe');

        await user.click(within(row('Tithe')).getByRole('button', { name: 'Change' }));
        const dialog = await screen.findByRole('dialog');
        expect(within(dialog).getByLabelText(/^Name/)).toHaveValue('Tithe');
        expect(within(dialog).getByLabelText('What it is for')).toHaveValue('One tenth of income.');
        await user.clear(within(dialog).getByLabelText(/^Name/));
        await user.type(within(dialog).getByLabelText(/^Name/), 'Tithes');
        await user.clear(within(dialog).getByLabelText('What it is for'));
        await user.click(within(dialog).getByRole('button', { name: 'Save changes' }));

        await waitFor(() =>
            expect(fundTypesApi.update).toHaveBeenCalledWith('fund-1', { name: 'Tithes', description: null, sort_order: 0 }),
        );
        expect(toast.success).toHaveBeenCalledWith('Changes saved.');
    });

    it('asks before switching a fund off, and only then does it', async () => {
        vi.mocked(fundTypesApi.setActive).mockResolvedValue({ ...missions, is_active: false });
        const user = userEvent.setup();
        open();
        await screen.findByText('Missions');

        await user.click(within(row('Missions')).getByRole('button', { name: 'Switch off' }));
        const question = await screen.findByRole('alertdialog');
        expect(question).toHaveTextContent('Switch off Missions?');
        expect(question).toHaveTextContent('Gifts already given to it stay on record');
        expect(fundTypesApi.setActive).not.toHaveBeenCalled();

        await user.click(within(question).getByRole('button', { name: 'Switch off' }));

        await waitFor(() => expect(fundTypesApi.setActive).toHaveBeenCalledWith('fund-2', false));
        expect(toast.success).toHaveBeenCalledWith('Missions is switched off.');
    });

    it('switches nothing off when the person cancels', async () => {
        const user = userEvent.setup();
        open();
        await screen.findByText('Missions');

        await user.click(within(row('Missions')).getByRole('button', { name: 'Switch off' }));
        await user.click(await screen.findByRole('button', { name: 'Cancel' }));

        expect(fundTypesApi.setActive).not.toHaveBeenCalled();
    });

    it('switches a fund back on without asking', async () => {
        vi.mocked(fundTypesApi.setActive).mockResolvedValue({ ...old, is_active: true });
        const user = userEvent.setup();
        open();
        await screen.findByText('Old Appeal');

        await user.click(within(row('Old Appeal')).getByRole('button', { name: 'Switch on' }));

        await waitFor(() => expect(fundTypesApi.setActive).toHaveBeenCalledWith('fund-3', true));
        expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument();
        expect(toast.success).toHaveBeenCalledWith('Old Appeal is in use again.');
    });

    it('says plainly when there are no funds', async () => {
        vi.mocked(fundTypesApi.all).mockResolvedValue([]);
        open();

        expect(await screen.findByText('No funds yet')).toBeInTheDocument();
    });

    it('says what went wrong and tries again', async () => {
        vi.mocked(fundTypesApi.all).mockRejectedValueOnce(new ApiError('The server is busy.', 500)).mockResolvedValue([tithe]);
        const user = userEvent.setup();
        open();

        expect(await screen.findByRole('alert')).toHaveTextContent('The server is busy.');
        await user.click(screen.getByRole('button', { name: 'Try again' }));

        expect(await screen.findByText('Tithe')).toBeInTheDocument();
    });
});
