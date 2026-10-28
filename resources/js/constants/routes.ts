/**
 * Every client-side path, defined once so no component hardcodes a URL string.
 */
export const ROUTES = {
    HOME: '/',
    LOGIN: '/login',
    DASHBOARD: '/dashboard',
    MEMBERS: '/members',
    MEMBER_NEW: '/members/new',
    MEMBER: (id: string) => `/members/${id}`,
    MEMBER_EDIT: (id: string) => `/members/${id}/edit`,
    FAMILIES: '/families',
    FAMILY: (id: string) => `/families/${id}`,
} as const;

/** The route patterns React Router matches; they use the same segments as ROUTES. */
export const ROUTE_PATTERNS = {
    MEMBER: '/members/:id',
    MEMBER_EDIT: '/members/:id/edit',
    FAMILY: '/families/:id',
} as const;
