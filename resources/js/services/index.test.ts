import type { AxiosResponse, InternalAxiosRequestConfig } from 'axios';
import { AxiosError } from 'axios';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from '@/services';
import { ApiError } from '@/services/ApiError';
import { useAuthStore } from '@/stores/useAuthStore';
import { useChurchStore } from '@/stores/useChurchStore';

const originalAdapter = api.defaults.adapter;

function reply(config: InternalAxiosRequestConfig, status: number, data: unknown = {}): Promise<AxiosResponse> {
    const response = { data, status, statusText: '', headers: {}, config } as AxiosResponse;

    return status < 400 ? Promise.resolve(response) : Promise.reject(new AxiosError('failed', String(status), config, null, response));
}

describe('api client', () => {
    beforeEach(() => {
        useAuthStore.setState({ user: { id: '1', name: 'A', phone: '+233244123456', email: null, roles: [], permissions: [] } });
        useChurchStore.setState({ church: { id: '1', name: 'Grace', slug: 'grace', plan: 'starter', sms_credits: 0 } });
    });

    afterEach(() => {
        api.defaults.adapter = originalAdapter;
    });

    it('sends the session cookie and never an Authorization header', async () => {
        let seen: InternalAxiosRequestConfig | undefined;
        api.defaults.adapter = (config) => {
            seen = config;
            return reply(config, 200);
        };

        await api.get('/auth/me');

        expect(seen?.withCredentials).toBe(true);
        expect(seen?.headers.has('Authorization')).toBe(false);
    });

    it('fetches the CSRF cookie before the first write, and only once', async () => {
        // The handshake is remembered for the page, so load a fresh copy of the client to see a new page load.
        vi.resetModules();
        const { api: freshApi } = await import('@/services');
        const calls: string[] = [];
        freshApi.defaults.adapter = (config) => {
            calls.push(`${config.method?.toUpperCase()} ${config.url}`);
            return reply(config, 200);
        };

        await freshApi.post('/auth/otp/request', {});
        await freshApi.post('/auth/otp/verify', {});

        expect(calls).toEqual(['GET /sanctum/csrf-cookie', 'POST /auth/otp/request', 'POST /auth/otp/verify']);
    });

    it('does not fetch the CSRF cookie for reads', async () => {
        vi.resetModules();
        const { api: freshApi } = await import('@/services');
        const calls: string[] = [];
        freshApi.defaults.adapter = (config) => {
            calls.push(`${config.method?.toUpperCase()} ${config.url}`);
            return reply(config, 200);
        };

        await freshApi.get('/auth/me');

        expect(calls).toEqual(['GET /auth/me']);
    });

    it('retries once with a new CSRF cookie after a 419', async () => {
        const calls: string[] = [];
        let first = true;
        api.defaults.adapter = (config) => {
            calls.push(`${config.method?.toUpperCase()} ${config.url}`);

            if (config.url === '/auth/logout' && first) {
                first = false;
                return reply(config, 419, { message: 'CSRF token mismatch.' });
            }

            return reply(config, 204);
        };

        await api.post('/auth/logout');

        expect(calls.filter((c) => c === 'POST /auth/logout')).toHaveLength(2);
        expect(calls).toContain('GET /sanctum/csrf-cookie');
    });

    it('does not retry a 419 forever', async () => {
        let attempts = 0;
        api.defaults.adapter = (config) => {
            if (config.url === '/auth/logout') {
                attempts += 1;
                return reply(config, 419, { message: 'CSRF token mismatch.' });
            }

            return reply(config, 204);
        };

        await expect(api.post('/auth/logout')).rejects.toMatchObject({ status: 419 });
        expect(attempts).toBe(2);
    });

    it('signs the user out locally on 401 and clears the church', async () => {
        api.defaults.adapter = (config) => reply(config, 401, { message: 'Unauthenticated.' });

        await expect(api.get('/auth/me')).rejects.toBeInstanceOf(ApiError);

        expect(useAuthStore.getState().user).toBeNull();
        expect(useChurchStore.getState().church).toBeNull();
    });

    it('turns validation errors into an ApiError with field messages', async () => {
        api.defaults.adapter = (config) => reply(config, 422, { message: 'Invalid', errors: { phone: ['Bad phone'] } });

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
