import { describe, expect, it, vi } from 'vitest';
import { showFieldErrors } from '@/lib/showFieldErrors';
import { ApiError } from '@/services/ApiError';

describe('showFieldErrors', () => {
    it('sets a message on each field the server complained about, and says which', () => {
        const setError = vi.fn();
        const error = new ApiError('Invalid', 422, { phone: ['Already used.'], email: ['Bad email.'] });

        const shown = showFieldErrors(error, setError, ['phone', 'first_name']);

        expect(shown).toEqual(['phone']);
        expect(setError).toHaveBeenCalledOnce();
        expect(setError).toHaveBeenCalledWith('phone', { message: 'Already used.' });
    });

    it('does nothing for an error that is not from the API', () => {
        const setError = vi.fn();

        expect(showFieldErrors(new Error('boom'), setError, ['phone'])).toEqual([]);
        expect(setError).not.toHaveBeenCalled();
    });
});
