import axios, { AxiosError } from 'axios';
import { ApiError } from '@/services/ApiError';
import { useAuthStore } from '@/stores/useAuthStore';
import { useChurchStore } from '@/stores/useChurchStore';
import type { ApiErrorBody } from '@/types/api';

declare module 'axios' {
    interface AxiosRequestConfig {
        /** Set on the retry after a 419, so a request is retried with a fresh CSRF cookie at most once. */
        csrfRetried?: boolean;
    }
}

/**
 * The one configured HTTP client. Everything else in the app reaches the API through services/api/.
 * The app and the API share an origin (the church's own subdomain), so the browser sends the httpOnly
 * session cookie by itself and the server resolves the church from the host.
 */
export const api = axios.create({
    baseURL: '/api/v1',
    headers: { Accept: 'application/json' },
    withCredentials: true,
    withXSRFToken: true,
});

let csrfCookie: Promise<void> | null = null;

/**
 * Asks Laravel to set the CSRF cookie once per page load; axios then copies it into the X-XSRF-TOKEN header.
 */
export function ensureCsrfCookie(): Promise<void> {
    csrfCookie ??= api
        .get('/sanctum/csrf-cookie', { baseURL: '/' })
        .then(() => undefined)
        .catch((error: unknown) => {
            csrfCookie = null;
            throw error;
        });

    return csrfCookie;
}

api.interceptors.request.use(async (config) => {
    if (config.method !== 'get') {
        await ensureCsrfCookie();
    }

    return config;
});

api.interceptors.response.use(
    (response) => response,
    (error: AxiosError<ApiErrorBody>) => {
        const status = error.response?.status ?? 0;
        const config = error.config;

        // 419 means the CSRF cookie went stale (for example the session expired). Fetch a new one and retry once.
        if (status === 419 && config !== undefined && !config.csrfRetried) {
            config.csrfRetried = true;
            csrfCookie = null;

            return api.request(config);
        }

        // A rejected session ends the visit; the auth guard then sends the user to sign in.
        if (status === 401 && useAuthStore.getState().user) {
            useAuthStore.getState().signOut();
            useChurchStore.getState().clearChurch();
        }

        return Promise.reject(
            error.response
                ? ApiError.fromBody(status, error.response.data)
                : new ApiError('Cannot reach the server. Check your connection.', 0),
        );
    },
);
