import { describe, expect, it, vi } from 'vitest';
import { api } from '@/services';
import { fundTypesApi } from '@/services/api/fundTypes';
import { fundType } from '@/test/fixtures';

vi.mock('@/services', () => ({ api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn() } }));

describe('fundTypesApi', () => {
    it('asks for the active funds only when choosing one for a gift, and for every fund when managing them', async () => {
        vi.mocked(api.get).mockResolvedValue({ data: { data: [fundType()] } });

        await fundTypesApi.active();
        expect(api.get).toHaveBeenLastCalledWith('/fund-types', { params: { is_active: 1, per_page: 100 } });

        await fundTypesApi.all();
        expect(api.get).toHaveBeenLastCalledWith('/fund-types', { params: { per_page: 100 } });
    });

    it('creates, changes and switches a fund, and unwraps the fund each answers with', async () => {
        const saved = fundType({ id: 'fund-9' });
        vi.mocked(api.post).mockResolvedValue({ data: { data: saved } });
        vi.mocked(api.put).mockResolvedValue({ data: { data: saved } });
        vi.mocked(api.patch).mockResolvedValue({ data: { data: saved } });

        await expect(fundTypesApi.create({ name: 'Harvest', description: null, sort_order: 4 })).resolves.toBe(saved);
        expect(api.post).toHaveBeenCalledWith('/fund-types', { name: 'Harvest', description: null, sort_order: 4 });

        await fundTypesApi.update('fund-9', { name: 'Harvest 2026' });
        expect(api.put).toHaveBeenCalledWith('/fund-types/fund-9', { name: 'Harvest 2026' });

        await fundTypesApi.setActive('fund-9', false);
        expect(api.patch).toHaveBeenCalledWith('/fund-types/fund-9', { is_active: false });
    });
});
