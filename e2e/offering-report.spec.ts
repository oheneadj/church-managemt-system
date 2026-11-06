import { expect, test } from '@playwright/test';
import { CHURCH_URL } from './helpers/config';

/** Today, typed the way the app asks for it: day first (dd/mm/yyyy). */
function today(): string {
    const date = new Date();

    return `${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}/${date.getFullYear()}`;
}

test.describe('the Sunday offering report', () => {
    test('shows the gift recorded at a service and prints without the app menu', async ({ page }) => {
        const name = `E2E Report ${Date.now().toString(36)}`;
        const cedis = 100 + Math.floor(Math.random() * 800);

        // A service for today, so the gift can be given at it.
        await page.goto(`${CHURCH_URL}/services`);
        await page.getByRole('button', { name: 'New service' }).first().click();
        await page.getByLabel(/^Name/).fill(name);
        await page.getByLabel(/^Date/).fill(today());
        await page.getByRole('button', { name: 'Create service' }).click();
        await expect(page.getByRole('heading', { level: 1, name })).toBeVisible();
        const servicePage = page.url();

        // A cash gift at that service.
        await page.goto(`${CHURCH_URL}/giving`);
        await page.getByRole('button', { name: 'Record a gift' }).click();
        const dialog = page.getByRole('dialog', { name: 'Record a cash gift' });
        await dialog.getByLabel(/^Fund/).selectOption({ label: 'Tithe' });
        await dialog.getByLabel(/^Amount/).fill(`${cedis}.37`);
        await dialog.getByLabel(/^Service/).selectOption({ label: name });
        await dialog.getByRole('button', { name: 'Record gift' }).click();
        await expect(page.getByText('Gift recorded.')).toBeVisible();

        // The report is one click from the service.
        await page.goto(servicePage);
        await page.getByRole('link', { name: 'Offering report' }).click();
        await expect(page.getByRole('heading', { level: 1, name: 'Offering report' })).toBeVisible();
        const atService = page.getByRole('region', { name: 'Given at this service' });
        await expect(atService).toContainText('Tithe');
        await expect(atService).toContainText(`GHS ${cedis}.37`);
        await expect(atService).toContainText('1 gift, 1 anonymous');
        await expect(page.getByText('Total for the day').locator('..')).toContainText(`GHS ${cedis}.37`);

        // On paper there is the report and nothing else: no menu, no sign-out, no print button.
        await expect(page.getByRole('navigation', { name: 'Main' })).toBeVisible();
        await page.emulateMedia({ media: 'print' });
        await expect(page.getByRole('navigation', { name: 'Main' })).toBeHidden();
        await expect(page.getByRole('button', { name: 'Sign out' })).toBeHidden();
        await expect(page.getByRole('button', { name: 'Print or save as PDF' })).toBeHidden();
        await expect(atService).toBeVisible();
    });
});
