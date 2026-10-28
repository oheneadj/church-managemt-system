/// <reference types="vite/client" />

interface ImportMetaEnv {
    /** The base domain churches live under ({slug}.{domain}); the bare domain is the platform's own page. */
    readonly VITE_TENANT_BASE_DOMAIN: string;
}
