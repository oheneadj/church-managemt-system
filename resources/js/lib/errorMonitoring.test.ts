import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const init = vi.fn();
const captureException = vi.fn();

vi.mock('@sentry/react', () => ({ init, captureException, breadcrumbsIntegration: vi.fn(() => ({})) }));

/** Loads a fresh copy of the module, because it remembers whether the monitor was started. */
async function freshModule() {
    vi.resetModules();

    return import('@/lib/errorMonitoring');
}

describe('errorMonitoring', () => {
    beforeEach(() => vi.clearAllMocks());
    afterEach(() => vi.unstubAllEnvs());

    it('does nothing when no address is set', async () => {
        vi.stubEnv('VITE_SENTRY_DSN', '');
        const { startErrorMonitoring, reportError } = await freshModule();

        startErrorMonitoring();
        reportError(new Error('boom'));
        await Promise.resolve();

        expect(init).not.toHaveBeenCalled();
        expect(captureException).not.toHaveBeenCalled();
    });

    it('starts once and turns every personal-data switch off', async () => {
        vi.stubEnv('VITE_SENTRY_DSN', 'https://key@monitor.example/1');
        const { startErrorMonitoring } = await freshModule();

        startErrorMonitoring();
        startErrorMonitoring();
        await vi.waitFor(() => expect(init).toHaveBeenCalledOnce());

        expect(init.mock.calls[0]?.[0].dataCollection).toEqual({
            userInfo: false,
            cookies: false,
            httpHeaders: false,
            httpBodies: [],
            urlQueryParams: false,
            stackFrameVariables: false,
        });
    });

    it('drops console breadcrumbs and keeps the others', async () => {
        vi.stubEnv('VITE_SENTRY_DSN', 'https://key@monitor.example/1');
        const { startErrorMonitoring } = await freshModule();
        startErrorMonitoring();
        await vi.waitFor(() => expect(init).toHaveBeenCalled());
        const beforeBreadcrumb = init.mock.calls[0]?.[0].beforeBreadcrumb;

        expect(beforeBreadcrumb({ category: 'console', message: 'Abena Asante' })).toBeNull();
        expect(beforeBreadcrumb({ category: 'navigation' })).toEqual({ category: 'navigation' });
    });

    it('sends a reported error to the monitor', async () => {
        vi.stubEnv('VITE_SENTRY_DSN', 'https://key@monitor.example/1');
        const { startErrorMonitoring, reportError } = await freshModule();
        const error = new Error('boom');

        startErrorMonitoring();
        reportError(error);

        await vi.waitFor(() => expect(captureException).toHaveBeenCalledWith(error));
    });
});
