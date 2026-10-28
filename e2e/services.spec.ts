import { expect, test } from '@playwright/test';
import { CHURCH_URL } from './helpers/config';

test.describe('running a Sunday service', () => {
    test('creates a service, opens check-in, adds a visitor and sees the count go up', async ({ page }) => {
        const stamp = Date.now().toString(36);
        const name = `E2E Service ${stamp}`;

        await page.goto(`${CHURCH_URL}/services`);
        await page.getByRole('button', { name: 'New service' }).first().click();
        await page.getByLabel(/^Name/).fill(name);
        await page.getByLabel(/^Date/).fill('2026-12-27');
        await page.getByRole('button', { name: 'Create service' }).click();

        // The service page opens, closed, with a check-in code.
        await expect(page.getByRole('heading', { level: 1, name })).toBeVisible();
        await expect(page.getByText('Check-in is closed', { exact: true })).toBeVisible();
        await expect(page.getByText(/Check-in code:/)).toBeVisible();

        await page.getByRole('button', { name: 'Open check-in' }).click();
        await expect(page.getByText('Check-in is open', { exact: true })).toBeVisible();

        // A visitor is counted straight away.
        await page.getByRole('button', { name: 'Add a visitor' }).click();
        await page.getByLabel(/^Name/).fill(`Visitor ${stamp}`);
        await page.getByRole('button', { name: 'Add visitor' }).click();
        await expect(page.getByRole('region', { name: 'Headcount' })).toContainText('1 checked in');
        await expect(page.getByText(`Visitor ${stamp}`).first()).toBeVisible();

        // The service shows in the list as open with one person in.
        await page.goto(`${CHURCH_URL}/services`);
        await expect(page.getByRole('link', { name: new RegExp(name) })).toContainText('1 checked in');
    });
});
