/** How many random bytes make a key: 16 bytes are 32 hex characters, far more than enough to never repeat. */
const KEY_BYTES = 16;

/**
 * A new key for a write that records something (docs/08, Idempotency). Send one key for each thing the person means
 * to do: if the request is repeated (a double click, a retry after a timeout) the server does it once. A new
 * key is made for the next thing. It is built from `getRandomValues`, not `randomUUID`, because `randomUUID` only
 * exists on secure pages (https or localhost) and church sites on plain http, such as local development, would break.
 */
export function newIdempotencyKey(): string {
    const bytes = crypto.getRandomValues(new Uint8Array(KEY_BYTES));

    return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
}

/** The header the server reads the key from. */
export const IDEMPOTENCY_HEADER = 'Idempotency-Key';
