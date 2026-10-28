import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from '@/services';
import { checkinApi } from '@/services/api/checkin';
import { servicesApi } from '@/services/api/services';
import { checkinService, qrCheckedIn, qrCode } from '@/test/fixtures';

vi.mock('@/services', () => ({ api: { get: vi.fn(), post: vi.fn() } }));

const respond = (data: unknown) => Promise.resolve({ data });

describe('checkinApi', () => {
    beforeEach(() => vi.clearAllMocks());

    it('asks which service a scanned code is for', async () => {
        vi.mocked(api.get).mockReturnValue(respond(checkinService()));

        expect((await checkinApi.service('tok')).name).toBe('Sunday Morning Service');
        expect(api.get).toHaveBeenCalledWith('/checkin/qr/tok');
    });

    it('asks for a texted code and then confirms it', async () => {
        vi.mocked(api.post)
            .mockReturnValueOnce(respond({ message: 'OTP sent', expires_in: 600 }))
            .mockReturnValueOnce(respond(qrCheckedIn()));

        await checkinApi.requestCode('tok', '0244123456');
        const result = await checkinApi.confirm('tok', '0244123456', '123456');

        expect(api.post).toHaveBeenNthCalledWith(1, '/checkin/qr/otp', { token: 'tok', phone: '0244123456' });
        expect(api.post).toHaveBeenNthCalledWith(2, '/checkin/qr', { token: 'tok', phone: '0244123456', otp: '123456' });
        expect(result.first_name).toBe('Emmanuel');
    });

    it('reads a fresh QR code for a service', async () => {
        vi.mocked(api.get).mockReturnValue(respond(qrCode()));

        expect((await servicesApi.qr('service-1')).refresh_in).toBe(60);
        expect(api.get).toHaveBeenCalledWith('/services/service-1/qr');
    });
});
