import { useSyncExternalStore } from 'react';

/**
 * Whether the screen matches a CSS media query, kept up to date as the window changes (a phone turned sideways, a window
 * dragged wider). It lets a page draw one layout or the other instead of both, so nothing is repeated for assistive technology.
 * Before the browser can say (and where it has no media queries) it answers false: the phone layout.
 */
export function useMediaQuery(query: string): boolean {
    return useSyncExternalStore(
        (notify) => {
            const list = window.matchMedia(query);
            list.addEventListener('change', notify);

            return () => list.removeEventListener('change', notify);
        },
        () => window.matchMedia(query).matches,
        () => false,
    );
}
