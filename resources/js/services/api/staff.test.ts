import { describe, expect, it, vi } from 'vitest';
import { api } from '@/services';
import { staffApi } from '@/services/api/staff';
import { pageOf } from '@/test/fixtures';

vi.mock('@/services', () => ({ api: { get: vi.fn() } }));

describe('staffApi.directory', () => {
    it('asks for the biggest page of staff names the server allows and unwraps the list', async () => {
        vi.mocked(api.get).mockResolvedValue({ data: pageOf([{ id: 'user-1', name: 'Abena Pastor' }]) });

        await expect(staffApi.directory()).resolves.toEqual([{ id: 'user-1', name: 'Abena Pastor' }]);
        expect(api.get).toHaveBeenCalledWith('/staff/directory', { params: { per_page: 100 } });
    });
});
