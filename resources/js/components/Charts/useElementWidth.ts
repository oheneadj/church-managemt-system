import { useEffect, useRef, useState } from 'react';

/**
 * The width of an element in pixels, kept up to date as it changes, so a chart can draw itself at its real size and keep its text
 * readable instead of scaling it. `fallback` is used until the first measurement, and where no resize observer exists.
 */
export function useElementWidth<T extends HTMLElement>(fallback: number) {
    const ref = useRef<T | null>(null);
    const [width, setWidth] = useState(fallback);

    useEffect(() => {
        const node = ref.current;

        if (node === null || typeof ResizeObserver === 'undefined') {
            return;
        }

        const observer = new ResizeObserver(([entry]) => {
            const measured = entry?.contentRect.width;

            if (measured !== undefined && measured > 0) {
                setWidth(Math.round(measured));
            }
        });
        observer.observe(node);

        return () => observer.disconnect();
    }, []);

    return [ref, width] as const;
}
