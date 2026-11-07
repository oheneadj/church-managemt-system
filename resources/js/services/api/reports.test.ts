import { describe, expect, it, vi } from 'vitest';
import { api } from '@/services';
import { reportsApi } from '@/services/api/reports';

vi.mock('@/services', () => ({ api: { get: vi.fn() } }));

describe('reportsApi.attendance', () => {
    it('asks for the weeks and the grouping under the names the API uses', async () => {
        vi.mocked(api.get).mockResolvedValue({ data: { group_by: 'week', data: [], summary: { average: 0, highest: 0, lowest: 0 } } });

        await reportsApi.attendance({ weeks: 26, groupBy: 'service' });

        expect(api.get).toHaveBeenCalledWith('/reports/attendance', { params: { weeks: 26, group_by: 'service' } });
    });
});

describe('reportsApi.giving', () => {
    it('asks for the months, and for one fund only when one is chosen', async () => {
        vi.mocked(api.get).mockResolvedValue({ data: { data: [], summary: {} } });

        await reportsApi.giving({ months: 12, fundId: '' });
        expect(api.get).toHaveBeenLastCalledWith('/reports/giving', { params: { months: 12 } });

        await reportsApi.giving({ months: 6, fundId: 'fund-1' });
        expect(api.get).toHaveBeenLastCalledWith('/reports/giving', { params: { months: 6, fund_type_id: 'fund-1' } });
    });
});
