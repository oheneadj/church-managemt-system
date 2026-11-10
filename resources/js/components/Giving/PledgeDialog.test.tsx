import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PledgeDialog } from '@/components/Giving/PledgeDialog';
import { ApiError } from '@/services/ApiError';
import { fundTypesApi } from '@/services/api/fundTypes';
import { membersApi } from '@/services/api/members';
import { pledgesApi } from '@/services/api/pledges';
import { fundType, memberSummary, pageOf, pledge } from '@/test/fixtures';
import type { Pledge } from '@/types/giving';
import { daysFromToday } from '@/utils/today';

vi.mock('@/services/api/pledges');
vi.mock('@/services/api/fundTypes');
vi.mock('@/services/api/members');

function open(existing?: Pledge) {
    const onOpenChange = vi.fn();
    render(
        <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })}>
            <PledgeDialog open onOpenChange={onOpenChange} pledge={existing} />
        </QueryClientProvider>,
    );

    return onOpenChange;
}

/** Searches for the member and chooses them. */
async function chooseMember(user: ReturnType<typeof userEvent.setup>) {
    await user.type(screen.getByLabelText('Member'), 'Emmanuel');
    await user.click(screen.getByRole('button', { name: 'Search' }));
    await user.click(await screen.findByRole('button', { name: 'Choose' }));
}

async function fundSelect() {
    await screen.findByRole('option', { name: 'Building Fund' });

    return screen.getByLabelText(/^Fund/);
}

describe('PledgeDialog: making a pledge', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.mocked(fundTypesApi.active).mockResolvedValue([
            fundType({ id: 'fund-1', name: 'Building Fund' }),
            fundType({ id: 'fund-2', name: 'Missions' }),
        ]);
        vi.mocked(membersApi.list).mockResolvedValue(pageOf([memberSummary({ id: 'member-1', full_name: 'Emmanuel Mensah' })]));
        vi.mocked(pledgesApi.create).mockResolvedValue(pledge());
    });

    it('makes a pledge for the chosen member and closes', async () => {
        const user = userEvent.setup();
        const onOpenChange = open();

        await chooseMember(user);
        await user.selectOptions(await fundSelect(), 'fund-1');
        await user.type(screen.getByLabelText(/What it is for/), 'Building Fund 2026 pledge');
        await user.type(screen.getByLabelText(/Amount pledged/), '1000');
        await user.type(screen.getByLabelText(/Target date/), daysFromToday(60));
        await user.click(screen.getByRole('button', { name: 'Make pledge' }));

        await waitFor(() => expect(pledgesApi.create).toHaveBeenCalledTimes(1));
        const [input, key] = vi.mocked(pledgesApi.create).mock.calls[0] ?? [];
        expect(input).toMatchObject({
            member_id: 'member-1',
            fund_type_id: 'fund-1',
            description: 'Building Fund 2026 pledge',
            amount_pledged: '1000',
            target_date: daysFromToday(60),
        });
        expect(key).toMatch(/^[0-9a-f]{32}$/);
        await waitFor(() => expect(onOpenChange).toHaveBeenCalledWith(false));
    });

    it('will not make a pledge for nobody', async () => {
        const user = userEvent.setup();
        open();

        await user.selectOptions(await fundSelect(), 'fund-1');
        await user.type(screen.getByLabelText(/What it is for/), 'Building');
        await user.type(screen.getByLabelText(/Amount pledged/), '50');
        await user.type(screen.getByLabelText(/Target date/), daysFromToday(10));
        await user.click(screen.getByRole('button', { name: 'Make pledge' }));

        expect(await screen.findByText('Choose the member who made the pledge')).toBeInTheDocument();
        expect(pledgesApi.create).not.toHaveBeenCalled();
    });

    it('shows what is missing and sends nothing', async () => {
        const user = userEvent.setup();
        open();

        await user.click(await screen.findByRole('button', { name: 'Make pledge' }));

        expect(await screen.findByText('Choose the fund')).toBeInTheDocument();
        expect(screen.getByText('Choose the member who made the pledge')).toBeInTheDocument();
        expect(screen.getByText('Say what the pledge is for')).toBeInTheDocument();
        expect(screen.getByText('Enter the amount')).toBeInTheDocument();
        expect(screen.getByText('Enter the target date')).toBeInTheDocument();
        expect(pledgesApi.create).not.toHaveBeenCalled();
    });

    it('refuses a target date in the past before asking the server', async () => {
        const user = userEvent.setup();
        open();

        await chooseMember(user);
        await user.selectOptions(await fundSelect(), 'fund-1');
        await user.type(screen.getByLabelText(/What it is for/), 'Building');
        await user.type(screen.getByLabelText(/Amount pledged/), '50');
        await user.type(screen.getByLabelText(/Target date/), daysFromToday(-3));
        await user.click(screen.getByRole('button', { name: 'Make pledge' }));

        expect(await screen.findByText('The target date must be today or later')).toBeInTheDocument();
        expect(pledgesApi.create).not.toHaveBeenCalled();
    });

    it("puts the server's message next to the field it belongs to and stays open", async () => {
        vi.mocked(pledgesApi.create).mockRejectedValue(
            new ApiError('The given data was invalid.', 422, {
                amount_pledged: ['The amount pledged field must not be greater than 999999.99.'],
            }),
        );
        const user = userEvent.setup();
        const onOpenChange = open();

        await chooseMember(user);
        await user.selectOptions(await fundSelect(), 'fund-1');
        await user.type(screen.getByLabelText(/What it is for/), 'Building');
        await user.type(screen.getByLabelText(/Amount pledged/), '50');
        await user.type(screen.getByLabelText(/Target date/), daysFromToday(10));
        await user.click(screen.getByRole('button', { name: 'Make pledge' }));

        expect(await screen.findByText('The amount pledged field must not be greater than 999999.99.')).toBeInTheDocument();
        expect(onOpenChange).not.toHaveBeenCalledWith(false);
    });
});

describe('PledgeDialog: changing a pledge', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.mocked(fundTypesApi.active).mockResolvedValue([fundType({ id: 'fund-1', name: 'Building Fund' })]);
        vi.mocked(pledgesApi.update).mockResolvedValue(pledge({ amount_pledged: '500.00' }));
    });

    it('shows the member and fund as words that cannot be changed, and fills in the rest', () => {
        open(pledge());

        expect(screen.getByText('Emmanuel Mensah')).toBeInTheDocument();
        expect(screen.queryByLabelText('Member')).not.toBeInTheDocument();
        expect(screen.queryByLabelText(/^Fund/)).not.toBeInTheDocument();
        expect(screen.getByLabelText(/What it is for/)).toHaveValue('Building Fund 2026 pledge');
        expect(screen.getByLabelText(/Amount pledged/)).toHaveValue('1000.00');
        expect(screen.getByLabelText(/Target date/)).toHaveValue('2026-12-31');
    });

    it('changes the amount and closes', async () => {
        const user = userEvent.setup();
        const onOpenChange = open(pledge());

        const amount = screen.getByLabelText(/Amount pledged/);
        await user.clear(amount);
        await user.type(amount, '500');
        await user.click(screen.getByRole('button', { name: 'Save changes' }));

        await waitFor(() =>
            expect(pledgesApi.update).toHaveBeenCalledWith('pledge-1', {
                description: 'Building Fund 2026 pledge',
                amount_pledged: '500',
                target_date: '2026-12-31',
            }),
        );
        await waitFor(() => expect(onOpenChange).toHaveBeenCalledWith(false));
    });

    it('can save a pledge whose date has passed as long as the date is not changed', async () => {
        const past = daysFromToday(-40);
        const user = userEvent.setup();
        open(pledge({ target_date: past }));

        await user.click(screen.getByRole('button', { name: 'Save changes' }));

        await waitFor(() => expect(pledgesApi.update).toHaveBeenCalledTimes(1));
        expect(screen.queryByText('The target date must be today or later')).not.toBeInTheDocument();
    });
});
