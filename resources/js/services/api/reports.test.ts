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
