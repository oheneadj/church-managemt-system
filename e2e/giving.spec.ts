import { expect, test } from '@playwright/test';
import { CHURCH_URL } from './helpers/config';

test.describe('recording giving', () => {
    test('records an anonymous cash gift, sees it in the list, then voids it with a reason', async ({ page }) => {
        // A different amount each run, so this run finds its own gift among any others.
        const cedis = 100 + Math.floor(Math.random() * 800);
        const amount = `${cedis}.37`;

        await page.goto(`${CHURCH_URL}/giving`);
        await page.getByRole('button', { name: 'Record a gift' }).click();

        const dialog = page.getByRole('dialog', { name: 'Record a cash gift' });
        await expect(dialog).toBeVisible();
        await dialog.getByLabel(/^Fund/).selectOption({ label: 'Tithe' });
        await dialog.getByLabel(/^Amount/).fill(amount);
        await dialog.getByRole('button', { name: 'Record gift' }).click();

        // The gift is on the list at once, as an anonymous cash gift.
        await expect(page.getByText('Gift recorded.')).toBeVisible();
        const gift = page
            .getByRole('listitem')
            .filter({ hasText: `GHS ${cedis}.37` })
            .first();
        await expect(gift).toContainText('Tithe');
        await expect(gift).toContainText('Anonymous');
        await expect(gift).toContainText('Cash');

        // Voiding needs a reason, and the gift stays on record, marked.
        await gift.getByRole('button', { name: 'Void' }).click();
        await page.getByRole('button', { name: 'Void gift' }).click();
        await expect(page.getByText('Say why this gift is being voided')).toBeVisible();
        await page.getByLabel(/^Reason/).fill('E2E test gift, voided to clean up');
        await page.getByRole('button', { name: 'Void gift' }).click();
        await expect(page.getByText('Gift voided.')).toBeVisible();
    });

    test('refuses a gift with no fund or amount without leaving the form', async ({ page }) => {
        await page.goto(`${CHURCH_URL}/giving`);
        await page.getByRole('button', { name: 'Record a gift' }).click();

        const dialog = page.getByRole('dialog', { name: 'Record a cash gift' });
        await dialog.getByRole('button', { name: 'Record gift' }).click();

        await expect(dialog.getByText('Choose the fund')).toBeVisible();
        await expect(dialog.getByText('Enter the amount')).toBeVisible();
    });
});
