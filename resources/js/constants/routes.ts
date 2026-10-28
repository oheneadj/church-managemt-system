/**
 * Every client-side path, defined once so no component hardcodes a URL string.
 */
export const ROUTES = {
    HOME: '/',
    LOGIN: '/login',
    DASHBOARD: '/dashboard',
} as const;
