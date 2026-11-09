import { describe, expect, it, vi } from 'vitest';
import { api } from '@/services';
import { platformApi } from '@/services/api/platform';
import { pageOf, platformAdmin, smsPackage } from '@/test/fixtures';

vi.mock('@/services', () => ({ api: { get: vi.fn(), post: vi.fn(), put: vi.fn() } }));

describe('platformApi', () => {
    it('signs in in two steps and unwraps the admin', async () => {
        vi.mocked(api.post)
            .mockResolvedValueOnce({ data: { message: 'Code sent', expires_in: 600 } })
            .mockResolvedValueOnce({ data: { data: platformAdmin() } });

        await platformApi.login('a@b.test', 'pw');
        expect(api.post).toHaveBeenLastCalledWith('/platform/auth/login', { email: 'a@b.test', password: 'pw' });

        await expect(platformApi.verifyLogin('a@b.test', '123456')).resolves.toEqual(platformAdmin());
        expect(api.post).toHaveBeenLastCalledWith('/platform/auth/login/verify', { email: 'a@b.test', code: '123456' });
    });

    it('reads who is signed in, and signs out', async () => {
        vi.mocked(api.get).mockResolvedValue({ data: { data: platformAdmin() } });
        vi.mocked(api.post).mockResolvedValue({ data: undefined });

        await expect(platformApi.me()).resolves.toEqual(platformAdmin());
        expect(api.get).toHaveBeenCalledWith('/platform/auth/me');
        await platformApi.logout();
        expect(api.post).toHaveBeenLastCalledWith('/platform/auth/logout');
    });

    it('lists every package, and adds and changes them with the price as exact text', async () => {
        vi.mocked(api.get).mockResolvedValue({ data: pageOf([smsPackage()]) });
        vi.mocked(api.post).mockResolvedValue({ data: { data: smsPackage() } });
        vi.mocked(api.put).mockResolvedValue({ data: { data: smsPackage() } });

        await expect(platformApi.packages()).resolves.toEqual([smsPackage()]);
        expect(api.get).toHaveBeenCalledWith('/platform/sms-packages', { params: { per_page: 100 } });

        await platformApi.createPackage({ name: 'A', credits: 10, price: '7', sort_order: 1 });
        expect(api.post).toHaveBeenLastCalledWith('/platform/sms-packages', { name: 'A', credits: 10, price: '7', sort_order: 1 });

        await platformApi.updatePackage('p1', { is_active: false });
        expect(api.put).toHaveBeenLastCalledWith('/platform/sms-packages/p1', { is_active: false });
    });
});
