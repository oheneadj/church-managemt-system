import type { FieldValues, Path, UseFormSetError } from 'react-hook-form';
import { ApiError } from '@/services/ApiError';

/**
 * Puts the server's validation messages next to the form fields they belong to.
 * Returns the fields that got a message, so a multi-step form can jump to the step that needs fixing.
 */
export function showFieldErrors<T extends FieldValues>(error: Error, setError: UseFormSetError<T>, fields: readonly Path<T>[]): Path<T>[] {
    if (!(error instanceof ApiError)) {
        return [];
    }

    const shown: Path<T>[] = [];

    for (const field of fields) {
        const message = error.fieldErrors[field]?.[0];

        if (message !== undefined) {
            setError(field, { message });
            shown.push(field);
        }
    }

    return shown;
}
