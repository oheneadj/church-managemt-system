import '@testing-library/jest-dom/vitest';
import { cleanup, configure } from '@testing-library/react';
import { afterEach } from 'vitest';

// findBy and waitFor wait 1 second by default. With every test file running in parallel on a busy machine a
// screen can take longer than that to appear, which showed up as random failures, so they wait up to 5 seconds
// (and still return the moment the thing appears).
configure({ asyncUtilTimeout: 5000 });

// jsdom has no matchMedia, which the toast library asks for to follow the system theme.
window.matchMedia ??= (query: string) =>
    ({
        matches: false,
        media: query,
        onchange: null,
        addListener: () => undefined,
        removeListener: () => undefined,
        addEventListener: () => undefined,
        removeEventListener: () => undefined,
        dispatchEvent: () => false,
    }) as MediaQueryList;

afterEach(() => cleanup());
