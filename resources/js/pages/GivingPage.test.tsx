import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PERMISSIONS, type Permission } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { GivingPage } from '@/pages/GivingPage';
import { contributionsApi } from '@/services/api/contributions';
import { fundTypesApi } from '@/services/api/fundTypes';
import { contribution, fundType, pageOf } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/services/api/contributions');
vi.mock('@/services/api/fundTypes');

const FINANCE: Permission[] = [PERMISSIONS.GIVING_VIEW, PERMISSIONS.GIVING_EDIT];

function open(permissions: Permission[] = FINANCE, url: string = ROUTES.GIVING) {
    return renderPage(<GivingPage />, { path: ROUTES.GIVING, url, permissions });
}

describe('GivingPage', () => {
    beforeEach(() => {
        vi.mocked(fundTypesApi.active).mockResolvedValue([fundType(), fundType({ id: 'fund-2', name: 'Offering' })]);
        vi.mocked(contributionsApi.list).mockResolvedValue(
            pageOf([
                contribution(),
                contribution({ id: 'gift-2', member: null, is_anonymous: true, amount_formatted: 'GHS 50.00' }),
                contribution({ id: 'gift-3', is_voided: true, void_reason: 'Entered twice', voided_by: 'Ama Boateng' }),
            ]),
        );
        vi.mocked(contributionsApi.void).mockResolvedValue(contribution({ is_voided: true }));
    });

    it('lists each gift with who gave, the fund, the amount and how', async () => {
        open();

        const [first] = await screen.findAllByRole('listitem');
        expect(first).toHaveTextContent('GHS 200.00');
        expect(first).toHaveTextContent('Tithe');
        expect(first).toHaveTextContent('Emmanuel Mensah');
        expect(first).toHaveTextContent('Cash');
        expect(screen.getByText('Anonymous', { exact: false })).toBeInTheDocument();
    });

    it('shows a voided gift with its reason and no Void button', async () => {
        open();

        const voided = (await screen.findByText('Entered twice', { exact: false })).closest('li') as HTMLElement;
        expect(within(voided).getByText('Voided')).toBeInTheDocument();
        expect(within(voided).queryByRole('button', { name: 'Void' })).not.toBeInTheDocument();
    });

    it('asks the server for the filters in the address', async () => {
        open(FINANCE, `${ROUTES.GIVING}?from=2026-10-01&fund=fund-2&method=cash&voided=1&page=2`);

        await waitFor(() =>
            expect(contributionsApi.list).toHaveBeenCalledWith({
                from: '2026-10-01',
                to: '',
                fund: 'fund-2',
                method: 'cash',
                voided: true,
                page: 2,
            }),
        );
    });

    it('ignores a filter value that is not valid', async () => {
        open(FINANCE, `${ROUTES.GIVING}?from=banana&method=gold`);

        await waitFor(() => expect(contributionsApi.list).toHaveBeenCalledWith(expect.objectContaining({ from: '', method: '' })));
    });

    it('voids a gift only after a reason is given', async () => {
        const user = userEvent.setup();
        open();

        const row = (await screen.findAllByRole('listitem'))[0] as HTMLElement;
        await user.click(within(row).getByRole('button', { name: 'Void' }));
        await user.click(screen.getByRole('button', { name: 'Void gift' }));
        expect(await screen.findByText('Say why this gift is being voided')).toBeInTheDocument();
        expect(contributionsApi.void).not.toHaveBeenCalled();

        await user.type(screen.getByLabelText(/Reason/), 'Entered twice by mistake');
        await user.click(screen.getByRole('button', { name: 'Void gift' }));

        await waitFor(() => expect(contributionsApi.void).toHaveBeenCalledWith('gift-1', 'Entered twice by mistake'));
    });

    it('hides Void from staff who may only view', async () => {
        open([PERMISSIONS.GIVING_VIEW]);

        await screen.findAllByRole('listitem');
        expect(screen.queryByRole('button', { name: 'Void' })).not.toBeInTheDocument();
    });

    it('says plainly when there are no gifts', async () => {
        vi.mocked(contributionsApi.list).mockResolvedValue(pageOf([]));
        open();

        expect(await screen.findByText('No gifts found')).toBeInTheDocument();
    });
});
