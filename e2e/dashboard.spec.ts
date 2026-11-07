import { expect, test } from '@playwright/test';
import { CHURCH_URL } from './helpers/config';

test.describe('dashboard', () => {
    test('the church admin sees the summary cards, and a card leads to the page behind it', async ({ page }) => {
        await page.goto(`${CHURCH_URL}/dashboard`);
        await expect(page.getByRole('heading', { level: 1, name: /^Welcome, / })).toBeVisible();

        // The church admin may read everything, so all three cards are there, each with a figure and never an error.
        await expect(page.getByRole('link', { name: /Active members/ })).toBeVisible();
        await expect(page.getByRole('link', { name: /Last service/ })).toBeVisible();
        await expect(page.getByRole('link', { name: /Given this month/ })).toBeVisible();
        await expect(page.getByRole('alert')).toHaveCount(0);

        await page.getByRole('link', { name: /Active members/ }).click();
        await expect(page).toHaveURL(/\/members\?status=active$/);
        await expect(page.getByRole('heading', { level: 1, name: 'Members' })).toBeVisible();
    });
});
