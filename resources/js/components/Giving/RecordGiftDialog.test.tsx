import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { RecordGiftDialog } from '@/components/Giving/RecordGiftDialog';
import { contributionsApi } from '@/services/api/contributions';
import { fundTypesApi } from '@/services/api/fundTypes';
import { membersApi } from '@/services/api/members';
import { servicesApi } from '@/services/api/services';
import { ApiError } from '@/services/ApiError';
import { contribution, fundType, memberSummary, pageOf, service } from '@/test/fixtures';
import { isoToDateText } from '@/utils/dateText';
import { daysFromToday } from '@/utils/today';

vi.mock('@/services/api/contributions');
vi.mock('@/services/api/fundTypes');
vi.mock('@/services/api/members');
vi.mock('@/services/api/services');

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
});
