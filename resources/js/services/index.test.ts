import type { AxiosAdapter, AxiosResponse, InternalAxiosRequestConfig } from 'axios';
import { AxiosError } from 'axios';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { api } from '@/services';
import { ApiError } from '@/services/ApiError';
import { useAuthStore } from '@/stores/useAuthStore';
import { useChurchStore } from '@/stores/useChurchStore';

const originalAdapter = api.defaults.adapter;

function respondWith(status: number, data: unknown): AxiosAdapter {
    return (config: InternalAxiosRequestConfig) => {
        const response = { data, status, statusText: '', headers: {}, config } as AxiosResponse;

        return status < 400 ? Promise.resolve(response) : Promise.reject(new AxiosError('failed', String(status), config, null, response));
    };
}

describe('api client', () => {
    beforeEach(() => {
        useAuthStore.setState({ token: '1|abc', user: null });
        useChurchStore.setState({ church: { id: '1', name: 'Grace', slug: 'grace', plan: 'starter', sms_credits: 0 } });
    });

    afterEach(() => {
        api.defaults.adapter = originalAdapter;
    });

    it('sends the bearer token', async () => {
        let sent: string | undefined;
        api.defaults.adapter = (config) => {
            sent = String(config.headers.Authorization);
            return respondWith(200, {})(config);
        };

        await api.get('/auth/me');

        expect(sent).toBe('Bearer 1|abc');
    });

    it('signs the user out on 401 and clears the church', async () => {
        api.defaults.adapter = respondWith(401, { message: 'Unauthenticated.' });

        await expect(api.get('/auth/me')).rejects.toBeInstanceOf(ApiError);

        expect(useAuthStore.getState().token).toBeNull();
        expect(useChurchStore.getState().church).toBeNull();
    });

    it('turns validation errors into an ApiError with field messages', async () => {
        api.defaults.adapter = respondWith(422, { message: 'Invalid', errors: { phone: ['Bad phone'] } });

        const error = await api.post('/auth/otp/request', {}).catch((e: unknown) => e);

        expect(error).toBeInstanceOf(ApiError);
        expect((error as ApiError).fieldErrors).toEqual({ phone: ['Bad phone'] });
        expect((error as ApiError).status).toBe(422);
    });

    it('reports a friendly message when the server cannot be reached', async () => {
        api.defaults.adapter = (config) => Promise.reject(new AxiosError('Network Error', 'ERR_NETWORK', config));

        const error = await api.get('/auth/me').catch((e: unknown) => e);

        expect(error).toMatchObject({ status: 0, message: 'Cannot reach the server. Check your connection.' });
    });
});
