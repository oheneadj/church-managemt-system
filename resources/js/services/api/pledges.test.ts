import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from '@/services';
import { pledgesApi } from '@/services/api/pledges';
import { pageOf, pledge } from '@/test/fixtures';

vi.mock('@/services', () => ({ api: { get: vi.fn(), post: vi.fn(), put: vi.fn() } }));

const respond = (data: unknown) => Promise.resolve({ data });

describe('pledgesApi', () => {
    beforeEach(() => vi.clearAllMocks());

    it('lists a page, sending only the filters that are set', async () => {
        vi.mocked(api.get).mockReturnValue(respond(pageOf([pledge()])));

        await pledgesApi.list({ status: 'partial', fund: '', page: 2 });

        expect(api.get).toHaveBeenCalledWith('/pledges', { params: { page: 2, status: 'partial' } });
    });

    it('sends the fund as fund_type_id', async () => {
        vi.mocked(api.get).mockReturnValue(respond(pageOf([])));

        await pledgesApi.list({ status: '', fund: 'fund-2', page: 1 });

        expect(api.get).toHaveBeenCalledWith('/pledges', { params: { page: 1, fund_type_id: 'fund-2' } });
    });

    it("asks for one member's pledges to one fund, as many as the server allows on a page", async () => {
        vi.mocked(api.get).mockReturnValue(respond(pageOf([pledge()])));

        const page = await pledgesApi.forGift('member-1', 'fund-1');

        expect(api.get).toHaveBeenCalledWith('/pledges', { params: { member_id: 'member-1', fund_type_id: 'fund-1', per_page: 100 } });
        expect(page.data).toHaveLength(1);
    });

    it('makes a pledge with the idempotency key in the header, and returns it without its wrapper', async () => {
        vi.mocked(api.post).mockReturnValue(respond({ data: pledge() }));
        const input = {
            member_id: 'member-1',
            fund_type_id: 'fund-1',
            description: 'Building',
            amount_pledged: '1000',
            target_date: '2026-12-31',
        };

        const made = await pledgesApi.create(input, 'key-0000-1111');

        expect(api.post).toHaveBeenCalledWith('/pledges', input, { headers: { 'Idempotency-Key': 'key-0000-1111' } });
        expect(made.id).toBe('pledge-1');
    });

    it('changes a pledge', async () => {
        vi.mocked(api.put).mockReturnValue(respond({ data: pledge({ amount_pledged: '500.00' }) }));

        const changed = await pledgesApi.update('pledge-1', { amount_pledged: '500' });

        expect(api.put).toHaveBeenCalledWith('/pledges/pledge-1', { amount_pledged: '500' });
        expect(changed.amount_pledged).toBe('500.00');
    });
});
