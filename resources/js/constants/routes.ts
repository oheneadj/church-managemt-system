/**
 * Every client-side path, defined once so no component hardcodes a URL string.
 */
export const ROUTES = {
    HOME: '/',
    LOGIN: '/login',
    START: '/start',
    DASHBOARD: '/dashboard',
    MEMBERS: '/members',
    MEMBER_NEW: '/members/new',
    MEMBER: (id: string) => `/members/${id}`,
    MEMBER_EDIT: (id: string) => `/members/${id}/edit`,
    SERVICES: '/services',
    SERVICE_SCHEDULES: '/services/schedules',
    SERVICE: (id: string) => `/services/${id}`,
    SERVICE_QR: (id: string) => `/services/${id}/qr`,
    /** The public page a member lands on after scanning the QR code. The server builds the same path (config/attendance.php). */
    CHECKIN: (token: string) => `/checkin/${token}`,
    GIVING: '/giving',
    FAMILIES: '/families',
    FAMILY: (id: string) => `/families/${id}`,
} as const;

/** The route patterns React Router matches; they use the same segments as ROUTES. */
export const ROUTE_PATTERNS = {
    MEMBER: '/members/:id',
    MEMBER_EDIT: '/members/:id/edit',
    FAMILY: '/families/:id',
    SERVICE: '/services/:id',
    SERVICE_QR: '/services/:id/qr',
    CHECKIN: '/checkin/:token',
} as const;
