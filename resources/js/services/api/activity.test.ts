import { describe, expect, it, vi } from 'vitest';
import { api } from '@/services';
import { activityApi } from '@/services/api/activity';
import { activityLine, pageOf } from '@/test/fixtures';

vi.mock('@/services', () => ({ api: { get: vi.fn() } }));

describe('activityApi.recent', () => {
    it('asks for the latest lines, as many as it is told', async () => {
        vi.mocked(api.get).mockResolvedValue({ data: pageOf([activityLine()]) });

        await expect(activityApi.recent(10)).resolves.toEqual(pageOf([activityLine()]));
        expect(api.get).toHaveBeenCalledWith('/activity', { params: { per_page: 10 } });
    });
});
