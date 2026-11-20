import { expect, test } from '@playwright/test';
import { CHURCH_URL } from './helpers/config';

/** A date some days from now, as the date input takes it (year, month, day: yyyy-mm-dd). */
function inDays(days: number): string {
    const date = new Date();
    date.setDate(date.getDate() + days);

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

test.describe('pledges', () => {
    test('adds a member, makes a pledge for them, lowers its amount, then pays part of it with a gift', async ({ page }) => {
        const stamp = Date.now().toString(36);
        const lastName = `Pledger${stamp}`;
        const description = `E2E pledge ${stamp}`;
        const phone = `0202${String(Date.now()).slice(-6)}`;

        // A member to pledge for.
        await page.goto(`${CHURCH_URL}/members/new`);
        await page.getByLabel(/First name/).fill('Kojo');
        await page.getByLabel(/Last name/).fill(lastName);
        await page.getByLabel(/Gender/).selectOption('male');
        await page.getByRole('button', { name: 'Next' }).click();
        await page.getByLabel(/^Phone number/).fill(phone);
        await page.getByRole('button', { name: 'Next' }).click();
        await page.getByRole('button', { name: 'Add member' }).click();
        await expect(page.getByRole('heading', { level: 1, name: new RegExp(lastName) })).toBeVisible();

        // Make the pledge from the menu's Pledges page.
        await page.getByRole('navigation', { name: 'Main' }).getByRole('link', { name: 'Pledges' }).click();
        await expect(page.getByRole('heading', { level: 1, name: 'Pledges' })).toBeVisible();
        await page.getByRole('button', { name: 'New pledge' }).click();
        const dialog = page.getByRole('dialog', { name: 'New pledge' });
        await dialog.getByLabel('Member').fill(lastName);
        await dialog.getByRole('button', { name: 'Search' }).click();
        await dialog.getByRole('button', { name: 'Choose' }).click();
        await dialog.getByLabel(/^Fund/).selectOption({ label: 'Building Fund' });
        await dialog.getByLabel(/What it is for/).fill(description);
        await dialog.getByLabel(/Amount pledged/).fill('1000.50');
        await dialog.getByLabel(/Target date/).fill(inDays(60));
        await dialog.getByRole('button', { name: 'Make pledge' }).click();
        await expect(page.getByText('Pledge made.')).toBeVisible();

        // It is on the list, nothing paid yet.
        const row = page.getByRole('listitem').filter({ hasText: description });
        await expect(row).toContainText(lastName);
        await expect(row).toContainText('Building Fund');
        await expect(row).toContainText('Paid GHS 0.00 of GHS 1,000.50');
        await expect(row.getByRole('progressbar')).toHaveAttribute('aria-valuenow', '0');
        await expect(row.getByText('Active')).toBeVisible();

        // Lowering the amount changes the amounts the server shows.
        await row.getByRole('button', { name: 'Change' }).click();
        const change = page.getByRole('dialog', { name: 'Change pledge' });
        await change.getByLabel(/Amount pledged/).fill('800');
        await change.getByRole('button', { name: 'Save changes' }).click();
        await expect(page.getByText('Pledge changed.')).toBeVisible();
        await expect(page.getByRole('listitem').filter({ hasText: description })).toContainText('Paid GHS 0.00 of GHS 800.00');

        // A gift that names the pledge moves its progress.
        await page.goto(`${CHURCH_URL}/giving`);
        await page.getByRole('button', { name: 'Record a gift' }).click();
        const gift = page.getByRole('dialog', { name: 'Record a cash gift' });
        await gift.getByLabel('Member (optional)').fill(lastName);
        await gift.getByRole('button', { name: 'Search' }).click();
        await gift.getByRole('button', { name: 'Choose' }).click();
        await gift.getByLabel(/^Fund/).selectOption({ label: 'Building Fund' });
        await gift.getByLabel(/^Amount/).fill('200');
        await gift.getByLabel(/Pays a pledge/).selectOption({ label: `${description} · GHS 800.00 outstanding` });
        await gift.getByRole('button', { name: 'Record gift' }).click();
        await expect(page.getByText('Gift recorded.')).toBeVisible();

        await page.goto(`${CHURCH_URL}/pledges`);
        const paid = page.getByRole('listitem').filter({ hasText: description });
        await expect(paid).toContainText('Paid GHS 200.00 of GHS 800.00');
        await expect(paid).toContainText('GHS 600.00 outstanding');
        await expect(paid.getByRole('progressbar')).toHaveAttribute('aria-valuenow', '25');
    });

    test('refuses a pledge with nothing filled in, without leaving the form', async ({ page }) => {
        await page.goto(`${CHURCH_URL}/pledges`);
        await page.getByRole('button', { name: 'New pledge' }).click();

        const dialog = page.getByRole('dialog', { name: 'New pledge' });
        await dialog.getByRole('button', { name: 'Make pledge' }).click();

        await expect(dialog.getByText('Choose the member who made the pledge')).toBeVisible();
        await expect(dialog.getByText('Choose the fund')).toBeVisible();
        await expect(dialog.getByText('Enter the amount')).toBeVisible();
    });
});
