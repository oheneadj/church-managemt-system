import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from '@/services';
import { onboardingApi } from '@/services/api/onboarding';

vi.mock('@/services', () => ({ api: { get: vi.fn(), post: vi.fn() } }));

const respond = (data: unknown) => Promise.resolve({ data });

describe('onboardingApi', () => {
    beforeEach(() => vi.clearAllMocks());

    it('asks for a sign-up code with the admin phone', async () => {
        vi.mocked(api.post).mockReturnValue(respond({ message: 'OTP sent', expires_in: 600 }));

        const result = await onboardingApi.requestOtp('0244300001');

        expect(api.post).toHaveBeenCalledWith('/onboarding/otp', { admin_phone: '0244300001' });
        expect(result.expires_in).toBe(600);
    });

    it('starts a church and returns where to sign in', async () => {
        const started = { church: { slug: 'victory' }, admin: { name: 'Kofi' }, login_url: 'https://victory.example.com/login' };
        vi.mocked(api.post).mockReturnValue(respond(started));

        const result = await onboardingApi.startChurch({
            church_name: 'Victory Chapel',
            member_number_prefix: 'VCI',
            admin_name: 'Kofi',
            admin_phone: '0244300001',
            otp: '123456',
        });

        expect(api.post).toHaveBeenCalledWith('/onboarding/churches', expect.objectContaining({ church_name: 'Victory Chapel' }));
        expect(result.login_url).toBe('https://victory.example.com/login');
    });
});
