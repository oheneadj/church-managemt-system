import { expect, test } from '@playwright/test';
import { CHURCH_URL, DEMO_PLATFORM_EMAIL, DEMO_PLATFORM_PASSWORD, PLATFORM_URL } from './helpers/config';
import { emailedCodeFor } from './helpers/signInCode';

// The platform owner's own side (D-069). It is on the platform host, with its own sign-in, apart from every church's.
test.describe('platform admin', () => {
    // These start signed out: the church admin's saved sign-in belongs to a church's address and must not be borrowed.
    test.use({ storageState: { cookies: [], origins: [] } });

    test('the owner signs in with an emailed code, manages an SMS package, and signs out', async ({ page }) => {
        // A package is switched off, never deleted, so each run leaves one switched-off package behind.
        const name = `E2E Package ${Date.now().toString(36)}`;

        await page.goto(`${PLATFORM_URL}/admin`);
        await expect(page.getByRole('heading', { level: 1, name: 'Platform sign in' })).toBeVisible();

        const before = new Date();
        await page.getByLabel(/Email address/).fill(DEMO_PLATFORM_EMAIL);
        await page.getByLabel(/Password/).fill(DEMO_PLATFORM_PASSWORD);
        await page.getByRole('button', { name: 'Continue' }).click();
        await page.getByLabel(/Code/).fill(await emailedCodeFor(DEMO_PLATFORM_EMAIL, before));
        await page.getByRole('button', { name: 'Sign in' }).click();

        await expect(page).toHaveURL(/\/admin\/sms-packages$/);
        await expect(page.getByRole('heading', { level: 1, name: 'SMS packages' })).toBeVisible();

        // Add a package.
        await page.getByRole('button', { name: 'Add a package' }).click();
        const add = page.getByRole('dialog');
        await add.getByLabel(/^Name/).fill(name);
        await add.getByLabel(/^Credits/).fill('250');
        await add.getByLabel(/^Price/).fill('19.99');
        await add.getByRole('button', { name: 'Add package' }).click();

        // A toast that names the package is also a list item, so the row is the one with a Change button.
        const row = page
            .getByRole('listitem')
            .filter({ hasText: name })
            .filter({ has: page.getByRole('button', { name: 'Change' }) });
        await expect(row).toBeVisible();
        await expect(row.getByText('250 credits')).toBeVisible();
        await expect(row.getByText('GHS 19.99')).toBeVisible();
        await expect(row.getByText('On sale', { exact: true })).toBeVisible();

        // The same name in capitals is refused beside the box.
        await page.getByRole('button', { name: 'Add a package' }).click();
        const again = page.getByRole('dialog');
        await again.getByLabel(/^Name/).fill(name.toUpperCase());
        await again.getByLabel(/^Credits/).fill('10');
        await again.getByLabel(/^Price/).fill('5.00');
        await again.getByRole('button', { name: 'Add package' }).click();
        await expect(again.getByRole('alert')).toBeVisible();
        await again.getByRole('button', { name: 'Cancel' }).click();

        // Switch it off, after being asked.
        await row.getByRole('button', { name: 'Switch off' }).click();
        await page.getByRole('alertdialog').getByRole('button', { name: 'Switch off' }).click();
        await expect(row.getByText('Switched off', { exact: true })).toBeVisible();

        // It is still there after a reload, and the owner stays signed in.
        await page.reload();
        await expect(row.getByText('Switched off', { exact: true })).toBeVisible();

        await page.getByRole('button', { name: 'Sign out' }).click();
        await expect(page).toHaveURL(/\/admin\/login$/);
        await page.goto(`${PLATFORM_URL}/admin/sms-packages`);
        await expect(page).toHaveURL(/\/admin\/login$/);
    });

    test("a church's address has no platform side", async ({ page }) => {
        await page.goto(`${CHURCH_URL}/admin/login`);

        await expect(page.getByRole('heading', { level: 1, name: 'Page not found' })).toBeVisible();

        const answer = await page.request.get(`${CHURCH_URL}/api/v1/platform/auth/me`);
        expect(answer.status()).toBe(404);
    });
});
