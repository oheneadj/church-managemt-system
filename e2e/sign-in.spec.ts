import { expect, test } from '@playwright/test';
import { CHURCH_URL } from './helpers/config';

// These start signed out. They use an email that has no account, so a rerun can never lock the demo admin out.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('staff sign-in screens (D-054)', () => {
    test('a wrong email or password gets one generic message and no code step', async ({ page }) => {
        await page.goto(`${CHURCH_URL}/login`);
        await page.getByLabel(/Email address/).fill(`nobody-${Date.now().toString(36)}@example.com`);
        await page.getByLabel(/Password/).fill('not the right passphrase');
        await page.getByRole('button', { name: 'Continue' }).click();

        await expect(page.getByText('Invalid email or password')).toBeVisible();
        await expect(page.getByLabel(/^Code/)).toHaveCount(0);
    });

    test('forgot password asks for an email, then moves to choosing a new password without saying if the account exists', async ({
        page,
    }) => {
        await page.goto(`${CHURCH_URL}/login`);
        await page.getByRole('link', { name: 'Forgot your password?' }).click();
        await expect(page.getByRole('heading', { level: 1, name: 'Forgot your password?' })).toBeVisible();

        await page.getByLabel(/Email address/).fill(`nobody-${Date.now().toString(36)}@example.com`);
        await page.getByRole('button', { name: 'Email me a code' }).click();

        await expect(page.getByRole('heading', { level: 1, name: 'Choose a new password' })).toBeVisible();
        await expect(page.getByText(/If .* belongs to a staff member/)).toBeVisible();
    });

    test('the password rules are checked in the browser before anything is sent', async ({ page }) => {
        await page.goto(`${CHURCH_URL}/forgot-password`);
        await page.getByLabel(/Email address/).fill(`nobody-${Date.now().toString(36)}@example.com`);
        await page.getByRole('button', { name: 'Email me a code' }).click();

        await page.getByLabel(/^Code/).fill('123456');
        await page.getByLabel(/^New password/).fill('short');
        await page.getByLabel(/Type the new password again/).fill('different');
        await page.getByRole('button', { name: 'Save new password' }).click();

        await expect(page.getByText(/Use at least 12 characters/)).toBeVisible();
        await expect(page.getByText('The two passwords do not match')).toBeVisible();
    });
});
