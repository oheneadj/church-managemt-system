import { expect, test } from '@playwright/test';
import { CHURCH_URL } from './helpers/config';

test.describe('the SMS credit balance', () => {
    test('is in the header on every page, and Top up explains that buying credits is coming', async ({ page }) => {
        await page.goto(`${CHURCH_URL}/dashboard`);

        const header = page.getByRole('banner');
        await expect(header.getByText('SMS credits')).toBeVisible();
        // A whole number, written with thousands separators.
        await expect(header.getByText(/^\d{1,3}(,\d{3})*$/)).toBeVisible();

        // It follows the person to other pages.
        await page.goto(`${CHURCH_URL}/giving`);
        await expect(page.getByRole('banner').getByText('SMS credits')).toBeVisible();

        // The church admin can press Top up; nothing is bought yet.
        await page.getByRole('button', { name: 'Top up' }).click();
        const dialog = page.getByRole('dialog', { name: 'Top up SMS credits' });
        await expect(dialog).toContainText('coming soon');
        await dialog.getByRole('button', { name: 'Got it' }).click();
        await expect(dialog).toBeHidden();
    });
});
