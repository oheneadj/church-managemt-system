import { useEffect, useState } from 'react';

/**
 * A value that follows the one given, but only after it has stopped changing for `delayMs`. It keeps a screen from asking
 * the server a question on every key press. The timer is the one outside system this syncs with, so an effect is right here.
 */
export function useDebouncedValue<T>(value: T, delayMs: number): T {
    const [settled, setSettled] = useState(value);

    useEffect(() => {
        const timer = setTimeout(() => setSettled(value), delayMs);

        return () => clearTimeout(timer);
    }, [value, delayMs]);

    return settled;
}
