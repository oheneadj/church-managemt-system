import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { RecordGiftDialog } from '@/components/Giving/RecordGiftDialog';
import { contributionsApi } from '@/services/api/contributions';
import { fundTypesApi } from '@/services/api/fundTypes';
import { membersApi } from '@/services/api/members';
import { pledgesApi } from '@/services/api/pledges';
import { servicesApi } from '@/services/api/services';
import { ApiError } from '@/services/ApiError';
import { contribution, fundType, memberSummary, pageOf, pledge, service } from '@/test/fixtures';
import { isoToDateText } from '@/utils/dateText';
import { daysFromToday } from '@/utils/today';

vi.mock('@/services/api/contributions');
vi.mock('@/services/api/fundTypes');
vi.mock('@/services/api/members');
vi.mock('@/services/api/services');
vi.mock('@/services/api/pledges');

function open() {
    const onOpenChange = vi.fn();
    render(
        <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })}>
            <RecordGiftDialog open onOpenChange={onOpenChange} />
        </QueryClientProvider>,
    );

    return onOpenChange;
}

/** The fund drop-down, once the funds have loaded into it. */
async function fundSelect() {
    await screen.findByRole('option', { name: 'Tithe' });

    return screen.getByLabelText(/Fund/);
}

describe('RecordGiftDialog', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.mocked(fundTypesApi.active).mockResolvedValue([fundType(), fundType({ id: 'fund-2', name: 'Offering' })]);
        vi.mocked(servicesApi.onDate).mockResolvedValue(pageOf([service({ id: 'service-1', name: 'Sunday Morning Service' })]));
        vi.mocked(membersApi.list).mockResolvedValue(pageOf([memberSummary({ id: 'member-1', full_name: 'Abena Asante' })]));
        vi.mocked(contributionsApi.record).mockResolvedValue(contribution());
        vi.mocked(pledgesApi.forGift).mockResolvedValue(pageOf([]));
    });

    it('records an anonymous gift when no member is chosen, and closes', async () => {
        const user = userEvent.setup();
        const onOpenChange = open();

        await user.selectOptions(await fundSelect(), 'fund-1');
        await user.type(screen.getByLabelText(/Amount/), '150.55');
        await user.click(screen.getByRole('button', { name: 'Record gift' }));

        await waitFor(() => expect(contributionsApi.record).toHaveBeenCalledTimes(1));
        const [input, key] = vi.mocked(contributionsApi.record).mock.calls[0] ?? [];
        expect(input).toMatchObject({ fund_type_id: 'fund-1', amount: '150.55', payment_method: 'cash' });
        expect(input?.member_id).toBeUndefined();
        expect(input?.contributed_at).toBeUndefined();
        expect(key).toMatch(/^[0-9a-f]{32}$/);
        await waitFor(() => expect(onOpenChange).toHaveBeenCalledWith(false));
    });

    it('records a gift for the member found by searching, at the chosen service', async () => {
        const user = userEvent.setup();
        open();

        await user.type(screen.getByLabelText('Member (optional)'), 'Abena');
        await user.click(screen.getByRole('button', { name: 'Search' }));
        await user.click(await screen.findByRole('button', { name: 'Choose' }));
        await user.selectOptions(await fundSelect(), 'fund-2');
        await user.type(screen.getByLabelText(/Amount/), '20');
        await screen.findByRole('option', { name: 'Sunday Morning Service' });
        await user.selectOptions(screen.getByRole('combobox', { name: /Service/ }), 'service-1');
        await user.click(screen.getByRole('button', { name: 'Record gift' }));

        await waitFor(() => expect(contributionsApi.record).toHaveBeenCalled());
        expect(vi.mocked(contributionsApi.record).mock.calls[0]?.[0]).toMatchObject({
            member_id: 'member-1',
            service_id: 'service-1',
            fund_type_id: 'fund-2',
        });
    });

    it('sends an earlier day at noon UTC', async () => {
        const user = userEvent.setup();
        open();
        const past = daysFromToday(-7);

        await user.selectOptions(await fundSelect(), 'fund-1');
        await user.type(screen.getByLabelText(/Amount/), '10');
        const date = screen.getByLabelText(/Date given/);
        await user.clear(date);
        await user.type(date, isoToDateText(past));
        await user.click(screen.getByRole('button', { name: 'Record gift' }));

        await waitFor(() => expect(contributionsApi.record).toHaveBeenCalled());
        expect(vi.mocked(contributionsApi.record).mock.calls[0]?.[0].contributed_at).toBe(`${past}T12:00:00Z`);
    });

    it('shows what is missing and sends nothing', async () => {
        const user = userEvent.setup();
        open();

        await user.click(await screen.findByRole('button', { name: 'Record gift' }));

        expect(await screen.findByText('Choose the fund')).toBeInTheDocument();
        expect(screen.getByText('Enter the amount')).toBeInTheDocument();
        expect(contributionsApi.record).not.toHaveBeenCalled();
    });

    it('refuses a future date without calling the server', async () => {
        const user = userEvent.setup();
        open();

        await user.selectOptions(await fundSelect(), 'fund-1');
        await user.type(screen.getByLabelText(/Amount/), '10');
        const date = screen.getByLabelText(/Date given/);
        await user.clear(date);
        await user.type(date, isoToDateText(daysFromToday(2)));
        await user.click(screen.getByRole('button', { name: 'Record gift' }));

        expect(await screen.findByText('A gift cannot be dated in the future')).toBeInTheDocument();
        expect(contributionsApi.record).not.toHaveBeenCalled();
    });

    it("puts the server's message next to the field it belongs to", async () => {
        vi.mocked(contributionsApi.record).mockRejectedValue(
            new ApiError('The given data was invalid.', 422, { amount: ['The amount field must not be greater than 999999.99.'] }),
        );
        const user = userEvent.setup();
        const onOpenChange = open();

        await user.selectOptions(await fundSelect(), 'fund-1');
        await user.type(screen.getByLabelText(/Amount/), '10');
        await user.click(screen.getByRole('button', { name: 'Record gift' }));

        expect(await screen.findByText('The amount field must not be greater than 999999.99.')).toBeInTheDocument();
        expect(onOpenChange).not.toHaveBeenCalledWith(false);
    });

    it('sends the same key again when the same attempt is repeated after a failure', async () => {
        vi.mocked(contributionsApi.record).mockRejectedValueOnce(new ApiError('Server error', 500, {}));
        const user = userEvent.setup();
        open();

        await user.selectOptions(await fundSelect(), 'fund-1');
        await user.type(screen.getByLabelText(/Amount/), '10');
        await user.click(screen.getByRole('button', { name: 'Record gift' }));
        await waitFor(() => expect(contributionsApi.record).toHaveBeenCalledTimes(1));
        await user.click(screen.getByRole('button', { name: 'Record gift' }));
        await waitFor(() => expect(contributionsApi.record).toHaveBeenCalledTimes(2));

        const calls = vi.mocked(contributionsApi.record).mock.calls;
        expect(calls[0]?.[1]).toBe(calls[1]?.[1]);
    });

    describe('paying a pledge', () => {
        /** Chooses the member found by searching. */
        async function chooseMember(user: ReturnType<typeof userEvent.setup>) {
            await user.type(screen.getByLabelText('Member (optional)'), 'Abena');
            await user.click(screen.getByRole('button', { name: 'Search' }));
            await user.click(await screen.findByRole('button', { name: 'Choose' }));
        }

        beforeEach(() => {
            vi.mocked(pledgesApi.forGift).mockResolvedValue(
                pageOf([
                    pledge({ id: 'pledge-1', description: 'Building 2026', amount_outstanding_formatted: 'GHS 700.00' }),
                    pledge({ id: 'pledge-done', description: 'Old pledge', status: 'fulfilled', status_label: 'Fulfilled' }),
                ]),
            );
        });

        it('offers the pledges that can take the gift once a member and a fund are chosen, and not ones paid in full', async () => {
            const user = userEvent.setup();
            open();

            await chooseMember(user);
            await user.selectOptions(await fundSelect(), 'fund-1');

            expect(await screen.findByRole('option', { name: 'Building 2026 · GHS 700.00 outstanding' })).toBeInTheDocument();
            expect(screen.queryByRole('option', { name: /Old pledge/ })).not.toBeInTheDocument();
            expect(pledgesApi.forGift).toHaveBeenCalledWith('member-1', 'fund-1');
        });

        it('sends the pledge the gift pays', async () => {
            const user = userEvent.setup();
            open();

            await chooseMember(user);
            await user.selectOptions(await fundSelect(), 'fund-1');
            await user.type(screen.getByLabelText(/Amount/), '200');
            await screen.findByRole('option', { name: /Building 2026/ });
            await user.selectOptions(screen.getByRole('combobox', { name: /Pays a pledge/ }), 'pledge-1');
            await user.click(screen.getByRole('button', { name: 'Record gift' }));

            await waitFor(() => expect(contributionsApi.record).toHaveBeenCalled());
            expect(vi.mocked(contributionsApi.record).mock.calls[0]?.[0]).toMatchObject({
                member_id: 'member-1',
                fund_type_id: 'fund-1',
                pledge_id: 'pledge-1',
            });
        });

        it('sends no pledge when none is chosen', async () => {
            const user = userEvent.setup();
            open();

            await chooseMember(user);
            await user.selectOptions(await fundSelect(), 'fund-1');
            await user.type(screen.getByLabelText(/Amount/), '200');
            await screen.findByRole('option', { name: /Building 2026/ });
            await user.click(screen.getByRole('button', { name: 'Record gift' }));

            await waitFor(() => expect(contributionsApi.record).toHaveBeenCalled());
            expect(vi.mocked(contributionsApi.record).mock.calls[0]?.[0].pledge_id).toBeUndefined();
        });

        it('does not ask about pledges for an anonymous gift', async () => {
            const user = userEvent.setup();
            open();

            await user.selectOptions(await fundSelect(), 'fund-1');

            expect(screen.queryByRole('combobox', { name: /Pays a pledge/ })).not.toBeInTheDocument();
            expect(pledgesApi.forGift).not.toHaveBeenCalled();
        });

        it('drops a pledge chosen for one fund when the fund is changed, instead of sending it', async () => {
            const user = userEvent.setup();
            open();

            await chooseMember(user);
            await user.selectOptions(await fundSelect(), 'fund-1');
            await user.type(screen.getByLabelText(/Amount/), '200');
            await screen.findByRole('option', { name: /Building 2026/ });
            await user.selectOptions(screen.getByRole('combobox', { name: /Pays a pledge/ }), 'pledge-1');
            vi.mocked(pledgesApi.forGift).mockResolvedValue(pageOf([]));
            await user.selectOptions(screen.getByLabelText(/^Fund/), 'fund-2');
            await user.click(screen.getByRole('button', { name: 'Record gift' }));

            await waitFor(() => expect(contributionsApi.record).toHaveBeenCalled());
            expect(vi.mocked(contributionsApi.record).mock.calls[0]?.[0].pledge_id).toBeUndefined();
        });

        it('carries on without the pledge field when the pledges cannot be read', async () => {
            vi.mocked(pledgesApi.forGift).mockRejectedValue(new ApiError('This action is unauthorized.', 403));
            const user = userEvent.setup();
            open();

            await chooseMember(user);
            await user.selectOptions(await fundSelect(), 'fund-1');
            await user.type(screen.getByLabelText(/Amount/), '200');
            await user.click(screen.getByRole('button', { name: 'Record gift' }));

            await waitFor(() => expect(contributionsApi.record).toHaveBeenCalled());
            expect(screen.queryByRole('combobox', { name: /Pays a pledge/ })).not.toBeInTheDocument();
        });
    });
});
