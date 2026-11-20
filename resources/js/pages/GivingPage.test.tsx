import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { PERMISSIONS, type Permission } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { GivingPage } from '@/pages/GivingPage';
import { contributionsApi } from '@/services/api/contributions';
import { exportsApi } from '@/services/api/exports';
import { fundTypesApi } from '@/services/api/fundTypes';
import { contribution, fundType, pageOf } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/services/api/contributions');
vi.mock('@/services/api/exports');
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

    it('offers Record a gift to staff who may record giving, and opens the form', async () => {
        const user = userEvent.setup();
        open([PERMISSIONS.GIVING_VIEW, PERMISSIONS.GIVING_RECORD]);

        await user.click(await screen.findByRole('button', { name: 'Record a gift' }));

        expect(await screen.findByRole('dialog', { name: 'Record a cash gift' })).toBeInTheDocument();
    });

    it('gives the form its own Fund field, apart from the Fund filter behind it', async () => {
        const user = userEvent.setup();
        open([PERMISSIONS.GIVING_VIEW, PERMISSIONS.GIVING_RECORD]);

        await user.click(await screen.findByRole('button', { name: 'Record a gift' }));
        const dialog = await screen.findByRole('dialog', { name: 'Record a cash gift' });

        // Two fields with the same id would tie the form's label to the filter's drop-down instead.
        expect(within(dialog).getByRole('combobox', { name: /^Fund/ })).toBeInTheDocument();
        const ids = [...document.querySelectorAll('[id]')].map((element) => element.id);
        expect(new Set(ids).size).toBe(ids.length);
    });

    it('hides Record a gift from staff who may not record giving', async () => {
        open([PERMISSIONS.GIVING_VIEW, PERMISSIONS.GIVING_EDIT]);

        await screen.findAllByRole('listitem');
        expect(screen.queryByRole('button', { name: 'Record a gift' })).not.toBeInTheDocument();
    });

    it('says plainly when there are no gifts', async () => {
        vi.mocked(contributionsApi.list).mockResolvedValue(pageOf([]));
        open();

        expect(await screen.findByText('No gifts found')).toBeInTheDocument();
    });
});

describe('GivingPage: export', () => {
    beforeEach(() => {
        vi.mocked(fundTypesApi.active).mockResolvedValue([fundType()]);
        vi.mocked(contributionsApi.list).mockResolvedValue(pageOf([contribution()]));
        vi.mocked(exportsApi.contributions).mockResolvedValue({ export_id: 'e1', message: 'Export queued.' });
    });

    it('shows the Export gifts button only to staff who hold giving.export, not to someone who can only record gifts', async () => {
        const first = open([PERMISSIONS.GIVING_VIEW, PERMISSIONS.GIVING_EXPORT]);
        await screen.findByText(/Emmanuel Mensah/);
        expect(screen.getByRole('button', { name: 'Export gifts' })).toBeInTheDocument();
        first.unmount();

        open([PERMISSIONS.GIVING_VIEW, PERMISSIONS.GIVING_RECORD]);
        await screen.findByText(/Emmanuel Mensah/);
        expect(screen.queryByRole('button', { name: 'Export gifts' })).not.toBeInTheDocument();
    });

    it('exports with the dates, fund and way of giving now on screen, and says voided gifts are included', async () => {
        const user = userEvent.setup();
        open([PERMISSIONS.GIVING_VIEW, PERMISSIONS.GIVING_EXPORT], '/giving?from=2026-10-01&to=2026-10-31&fund=fund-1&method=cash');
        await screen.findByText(/Emmanuel Mensah/);

        await user.click(screen.getByRole('button', { name: 'Export gifts' }));
        expect(await screen.findByRole('alertdialog')).toHaveTextContent('Voided gifts are included and marked');
        await user.click(screen.getByRole('button', { name: 'Email me the link' }));

        await waitFor(() => expect(exportsApi.contributions).toHaveBeenCalledTimes(1));
        expect(vi.mocked(exportsApi.contributions).mock.calls[0]?.[0]).toMatchObject({
            from: '2026-10-01',
            to: '2026-10-31',
            fund: 'fund-1',
            method: 'cash',
        });
    });
});

describe('GivingPage on a wide screen', () => {
    const original = window.matchMedia;

    beforeEach(() => {
        window.matchMedia = ((query: string) =>
            ({
                matches: true,
                media: query,
                addEventListener: () => undefined,
                removeEventListener: () => undefined,
            }) as unknown as MediaQueryList) as typeof window.matchMedia;
        vi.mocked(fundTypesApi.active).mockResolvedValue([fundType()]);
        vi.mocked(contributionsApi.list).mockResolvedValue(
            pageOf(
                [
                    contribution(),
                    contribution({
                        id: 'gift-3',
                        amount_formatted: 'GHS 75.00',
                        is_voided: true,
                        void_reason: 'Entered twice',
                        voided_by: 'Ama Boateng',
                    }),
                ],
                { total: 2 },
            ),
        );
        vi.mocked(contributionsApi.void).mockResolvedValue(contribution({ is_voided: true }));
    });

    afterEach(() => {
        window.matchMedia = original;
    });

    it("draws the gifts as a table, not a list of cards, with the server's amounts", async () => {
        open();

        const table = await screen.findByRole('table', { name: 'Gifts, newest first' });
        expect(within(table).getByText('GHS 200.00')).toBeInTheDocument();
        expect(screen.queryByRole('list')).toBeNull();
        expect(screen.getByText('2 gifts found')).toBeInTheDocument();
    });

    it('shows a table of placeholder rows while the first page loads', () => {
        vi.mocked(contributionsApi.list).mockReturnValue(new Promise(() => undefined));
        open();

        expect(screen.getByRole('table')).toHaveAttribute('aria-busy', 'true');
        expect(within(screen.getByRole('table')).getByRole('status')).toHaveTextContent('Loading');
    });

    it('still asks the server for the filters in the address', async () => {
        open(FINANCE, `${ROUTES.GIVING}?from=2026-10-01&fund=fund-1&page=2`);

        await screen.findByRole('table', { name: 'Gifts, newest first' });
        expect(contributionsApi.list).toHaveBeenCalledWith(expect.objectContaining({ from: '2026-10-01', fund: 'fund-1', page: 2 }));
    });

    it('lets staff who may void a gift do so from the table, after the usual confirmation, and never a voided one', async () => {
        const user = userEvent.setup();
        open();
        await screen.findByRole('table', { name: 'Gifts, newest first' });

        expect(screen.getAllByRole('button', { name: /^Void the gift of/ })).toHaveLength(1);
        await user.click(screen.getByRole('button', { name: 'Void the gift of GHS 200.00 to Tithe' }));

        expect(await screen.findByRole('dialog')).toBeInTheDocument();
        expect(contributionsApi.void).not.toHaveBeenCalled();
    });

    it('offers no Void button to staff who may not correct giving', async () => {
        open([PERMISSIONS.GIVING_VIEW]);

        await screen.findByRole('table', { name: 'Gifts, newest first' });
        expect(screen.queryByRole('button', { name: /^Void/ })).toBeNull();
        expect(screen.queryByRole('columnheader', { name: 'Actions' })).toBeNull();
    });

    it('still says plainly when no gifts were found', async () => {
        vi.mocked(contributionsApi.list).mockResolvedValue(pageOf([], { total: 0 }));
        open();

        expect(await screen.findByText('No gifts found')).toBeInTheDocument();
        expect(screen.queryByRole('table')).toBeNull();
    });
});
