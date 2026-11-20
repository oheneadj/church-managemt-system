import { describe, expect, it, vi } from 'vitest';
import { api } from '@/services';
import { departmentsApi } from '@/services/api/departments';
import { department, pageOf } from '@/test/fixtures';

vi.mock('@/services', () => ({ api: { get: vi.fn(), post: vi.fn(), put: vi.fn() } }));

describe('departmentsApi', () => {
    it('asks for the active departments when choosing one, and for every department when managing them', async () => {
        vi.mocked(api.get).mockResolvedValue({ data: pageOf([department()]) });

        await departmentsApi.list();
        expect(api.get).toHaveBeenLastCalledWith('/departments', { params: { per_page: 100, is_active: 1 } });

        await expect(departmentsApi.all()).resolves.toEqual([department()]);
        expect(api.get).toHaveBeenLastCalledWith('/departments', { params: { per_page: 100 } });
    });

    it('creates, changes and retires a department, and unwraps the department each answers with', async () => {
        const saved = department({ id: 'dept-9' });
        vi.mocked(api.post).mockResolvedValue({ data: { data: saved } });
        vi.mocked(api.put).mockResolvedValue({ data: { data: saved } });

        await expect(departmentsApi.create({ name: 'Youth', description: null })).resolves.toBe(saved);
        expect(api.post).toHaveBeenCalledWith('/departments', { name: 'Youth', description: null });

        await departmentsApi.update('dept-9', { name: 'Youth 2' });
        expect(api.put).toHaveBeenLastCalledWith('/departments/dept-9', { name: 'Youth 2' });

        await departmentsApi.setActive('dept-9', false);
        expect(api.put).toHaveBeenLastCalledWith('/departments/dept-9', { is_active: false });
    });
});
