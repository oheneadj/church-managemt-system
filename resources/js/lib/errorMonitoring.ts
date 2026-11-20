/**
 * Reports errors in the browser to the error monitor (docs/17). The Sentry code is loaded on demand and only when
 * VITE_SENTRY_DSN is set, so it costs nothing in the main bundle and nothing at all in development.
 *
 * Sentry 11 collects personal data unless told not to, so every collection switch is turned off here: no user
 * details, cookies, headers, request bodies, query strings (a search box holds names and phone numbers), page text
 * from clicks, or local variables. An error carries its stack trace and the page path, and nothing else.
 */
type SentryModule = typeof import('@sentry/react');

let monitor: Promise<SentryModule> | null = null;

/** Starts the monitor once. Does nothing when no address is configured. */
export function startErrorMonitoring(): void {
    const dsn = import.meta.env.VITE_SENTRY_DSN;

    if (!dsn || monitor !== null) {
        return;
    }

    monitor = import('@sentry/react').then((Sentry) => {
        Sentry.init({
            dsn,
            environment: import.meta.env.VITE_SENTRY_ENVIRONMENT,
            release: import.meta.env.VITE_SENTRY_RELEASE,
            dataCollection: {
                userInfo: false,
                cookies: false,
                httpHeaders: false,
                httpBodies: [],
                urlQueryParams: false,
                stackFrameVariables: false,
            },
            // Clicks and console lines can hold names, so they are not kept as breadcrumbs.
            integrations: [Sentry.breadcrumbsIntegration({ dom: false })],
            beforeBreadcrumb: (breadcrumb) => (breadcrumb.category === 'console' ? null : breadcrumb),
        });

        return Sentry;
    });
}

/** Sends one error to the monitor, if it is running. A monitor that fails to load never breaks the app. */
export function reportError(error: unknown): void {
    void monitor?.then((Sentry) => Sentry.captureException(error)).catch(() => undefined);
}
