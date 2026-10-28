/**
 * Shapes returned by the /api/v1 endpoints that the app consumes (docs/08-api.md).
 */

export interface User {
    id: string;
    name: string;
    phone: string;
    email: string | null;
    roles: string[];
    permissions: string[];
}

export interface Church {
    id: string;
    name: string;
    slug: string;
    plan: string;
    sms_credits: number;
}

export interface OtpRequested {
    message: string;
    expires_in: number;
}

/**
 * What sign-in and "who am I" return. The browser app is signed in by a cookie, so there is no token here.
 */
export interface Session {
    user: User;
    church: Church;
}

/** The error shape every endpoint returns; `errors` is present for validation failures. */
export interface ApiErrorBody {
    message: string;
    errors?: Record<string, string[]>;
}
