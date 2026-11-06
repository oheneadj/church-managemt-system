import { ApiError } from '@/services/ApiError';

/**
 * The message to show above a form for a failed request, or null when there is nothing to show: no failure, or a
 * validation failure whose messages already sit next to their boxes.
 */
export function plainErrorMessage(error: Error | null): string | null {
    if (error === null) {
        return null;
    }

    return error instanceof ApiError && Object.keys(error.fieldErrors).length > 0 ? null : error.message;
}
