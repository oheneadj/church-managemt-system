import axios, { AxiosError } from 'axios';
import { ApiError } from '@/services/ApiError';
import { useAuthStore } from '@/stores/useAuthStore';
import { useChurchStore } from '@/stores/useChurchStore';
import type { ApiErrorBody } from '@/types/api';

/**
 * The one configured HTTP client. Everything else in the app reaches the API through services/api/.
 * Requests go to the same origin, so the church is resolved from the subdomain by the server.
 */
export const api = axios.create({
    baseURL: '/api/v1',
    headers: { Accept: 'application/json' },
});

api.interceptors.request.use((config) => {
    const token = useAuthStore.getState().token;

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
});

api.interceptors.response.use(
    (response) => response,
    (error: AxiosError<ApiErrorBody>) => {
        const status = error.response?.status ?? 0;

        // An expired or revoked token ends the session; the auth guard then sends the user to sign in.
        if (status === 401 && useAuthStore.getState().token) {
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
