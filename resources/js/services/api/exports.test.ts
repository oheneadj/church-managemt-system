import { describe, expect, it, vi } from 'vitest';
import { api } from '@/services';
import { exportsApi } from '@/services/api/exports';

vi.mock('@/services', () => ({ api: { post: vi.fn() } }));

describe('exportsApi', () => {
    it('asks for the member list with the filters in the body, and no page', async () => {
        vi.mocked(api.post).mockResolvedValue({ data: { export_id: 'e1', message: 'Export queued.' } });

        await expect(exportsApi.members({ search: 'Ab', status: 'active', gender: '', department_id: '', page: 4 })).resolves.toEqual({
            export_id: 'e1',
            message: 'Export queued.',
        });
        expect(api.post).toHaveBeenCalledWith('/exports/members', { search: 'Ab', status: 'active' });
    });

    it('asks for the gifts with the filters in the body, leaving out the voided switch and the page', async () => {
        vi.mocked(api.post).mockResolvedValue({ data: { export_id: 'e2', message: 'Export queued.' } });

        await exportsApi.contributions({ from: '2026-10-01', to: '', fund: 'fund-1', method: '', voided: true, page: 2 });

        expect(api.post).toHaveBeenCalledWith('/exports/contributions', { from: '2026-10-01', fund_type_id: 'fund-1' });
    });
});
