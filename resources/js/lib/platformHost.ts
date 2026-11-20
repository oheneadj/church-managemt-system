/**
 * True when the app was opened on the platform's own address (the bare base domain) rather than on a church's
 * subdomain. Only the "start a church" page works there, because no church has been chosen yet.
 */
export function isPlatformHost(): boolean {
    return window.location.hostname === import.meta.env.VITE_TENANT_BASE_DOMAIN;
}
