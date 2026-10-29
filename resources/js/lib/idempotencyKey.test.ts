import { describe, expect, it } from 'vitest';
import { newIdempotencyKey } from '@/lib/idempotencyKey';

describe('newIdempotencyKey', () => {
    it('makes a key the server accepts: 8 to 100 letters, digits, dashes and underscores', () => {
        expect(newIdempotencyKey()).toMatch(/^[A-Za-z0-9_-]{8,100}$/);
    });

    it('makes a different key each time', () => {
        expect(newIdempotencyKey()).not.toBe(newIdempotencyKey());
    });

    it('does not need a secure page: it works when randomUUID is missing', () => {
        const original = crypto.randomUUID;
        // @ts-expect-error randomUUID is removed on purpose, as in a page served over plain http
        crypto.randomUUID = undefined;

        try {
            expect(newIdempotencyKey()).toMatch(/^[0-9a-f]{32}$/);
        } finally {
            crypto.randomUUID = original;
        }
    });
});
