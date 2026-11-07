import { expect, test } from '@playwright/test';
import { CHURCH_URL } from './helpers/config';

test.describe('giving funds', () => {
    test('the church admin adds a fund, sees it offered for gifts, is told a name is taken, and switches it off', async ({ page }) => {
        // Funds cannot be deleted, only switched off, so each run leaves one switched-off fund behind.
        const name = `E2E Fund ${Date.now().toString(36)}`;

        await page.goto(`${CHURCH_URL}/dashboard`);
        await page.getByRole('link', { name: 'Settings' }).click();
        await page.getByRole('link', { name: 'Giving funds' }).click();
        await expect(page.getByRole('heading', { level: 1, name: 'Giving funds' })).toBeVisible();

        // Add.
        await page.getByRole('button', { name: 'Add a fund' }).click();
        const add = page.getByRole('dialog');
        await add.getByLabel(/^Name/).fill(name);
        await add.getByLabel('What it is for').fill('Made by an automated test.');
        await add.getByRole('button', { name: 'Add fund' }).click();

        // A toast that names the fund is also a list item, so the row is the one with a Change button.
        const row = page
            .getByRole('listitem')
            .filter({ hasText: name })
            .filter({ has: page.getByRole('button', { name: 'Change' }) });
        await expect(row).toBeVisible();
        await expect(row.getByText('In use', { exact: true })).toBeVisible();

        // It is offered when a gift is recorded.
        await page.goto(`${CHURCH_URL}/giving`);
        await page.getByRole('button', { name: 'Record a gift' }).click();
        await expect(page.getByRole('dialog').getByRole('option', { name })).toBeAttached();
        await page.keyboard.press('Escape');

        // The same name again, in capitals, is refused beside the box.
        await page.goto(`${CHURCH_URL}/settings/funds`);
        await page.getByRole('button', { name: 'Add a fund' }).click();
        const again = page.getByRole('dialog');
        await again.getByLabel(/^Name/).fill(name.toUpperCase());
        await again.getByRole('button', { name: 'Add fund' }).click();
        await expect(again.getByRole('alert')).toBeVisible();
        await again.getByRole('button', { name: 'Cancel' }).click();

        // Switch it off, after being asked.
        await row.getByRole('button', { name: 'Switch off' }).click();
        await page.getByRole('alertdialog').getByRole('button', { name: 'Switch off' }).click();
        await expect(row.getByText('Switched off', { exact: true })).toBeVisible();

        // It is no longer offered for gifts.
        await page.goto(`${CHURCH_URL}/giving`);
        await page.getByRole('button', { name: 'Record a gift' }).click();
        await expect(page.getByRole('dialog').getByRole('option', { name: 'Tithe' })).toBeAttached();
        await expect(page.getByRole('dialog').getByRole('option', { name })).toHaveCount(0);
    });
});
