import type { ApiErrorBody } from '@/types/api';

/**
 * A failed API call, normalised so call sites never inspect raw Axios errors.
 */
export class ApiError extends Error {
    constructor(
        message: string,
        readonly status: number,
        readonly fieldErrors: Record<string, string[]> = {},
    ) {
        super(message);
        this.name = 'ApiError';
    }

    static fromBody(status: number, body: Partial<ApiErrorBody> | undefined): ApiError {
        return new ApiError(body?.message ?? 'Something went wrong. Please try again.', status, body?.errors);
    }
}
