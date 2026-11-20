/// <reference types="vite/client" />

interface ImportMetaEnv {
    /** The base domain churches live under ({slug}.{domain}); the bare domain is the platform's own page. */
    readonly VITE_TENANT_BASE_DOMAIN: string;
    /** Where browser errors are sent. Empty means they are not reported. */
    readonly VITE_SENTRY_DSN?: string;
    readonly VITE_SENTRY_ENVIRONMENT?: string;
    /** The deploy's commit, so a new error shows which release brought it. */
    readonly VITE_SENTRY_RELEASE?: string;
}
