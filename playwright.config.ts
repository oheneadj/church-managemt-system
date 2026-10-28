import { defineConfig } from '@playwright/test';

/**
 * End-to-end tests drive a real browser against a running copy of the app (React addendum, section 12).
 * Point them at a copy with E2E_PLATFORM_URL (the bare domain, where churches sign up) and E2E_CHURCH_URL (a
 * church's address). The defaults are the local Valet sites. Staging should be used once it exists.
 *
 * The tests read sign-in codes from the application log, so they only work against a copy that uses the
 * "log" SMS driver and shares this folder's storage/logs. A queue worker for the `critical` queue must be running.
 *
 * Starting a church texts a code, and the code is limited to 10 per IP address per hour, so a full run (3 codes)
 * can be repeated about 3 times an hour. The demo admin can ask for one code a minute. Wait if a run reports that
 * no code arrived while the worker is running.
 */
export default defineConfig({
    testDir: './e2e',
    timeout: 60_000,
    expect: { timeout: 10_000 },
    // The sign-in code is limited to one per minute per phone, so tests share one browser and run one at a time.
    workers: 1,
    fullyParallel: false,
    reporter: [['list']],
    use: {
        // Uses the Chrome that is installed on the machine, so no browser has to be downloaded.
        channel: 'chrome',
        trace: 'retain-on-failure',
    },
    projects: [
        // Signs in once and saves the browser state; the specs that need an admin start from it.
        { name: 'setup', testMatch: /auth\.setup\.ts/ },
        { name: 'chrome', testIgnore: /auth\.setup\.ts/, dependencies: ['setup'], use: { storageState: 'e2e/.auth/admin.json' } },
    ],
});
