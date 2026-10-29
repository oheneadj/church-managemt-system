import { expect, test } from '@playwright/test';
import { CHURCH_URL } from './helpers/config';

/** A date some days from now (0 is today), typed the way the app asks for it: day first (dd/mm/yyyy). */
function inDays(days: number): string {
    const date = new Date();
    date.setDate(date.getDate() + days);

    return `${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}/${date.getFullYear()}`;
}

test.describe('running a Sunday service', () => {
    test('creates a service, opens check-in, adds a visitor and sees the count go up', async ({ page }) => {
        const stamp = Date.now().toString(36);
        const name = `E2E Service ${stamp}`;

        await page.goto(`${CHURCH_URL}/services`);
        await page.getByRole('button', { name: 'New service' }).first().click();
        await page.getByLabel(/^Name/).fill(name);
        await page.getByLabel(/^Date/).fill(inDays(0));
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

    test('shows a QR code that opens the public check-in page for the service', async ({ page, browser }) => {
        const name = `E2E QR ${Date.now().toString(36)}`;

        await page.goto(`${CHURCH_URL}/services`);
        await page.getByRole('button', { name: 'New service' }).first().click();
        await page.getByLabel(/^Name/).fill(name);
        await page.getByLabel(/^Date/).fill(inDays(0));
        await page.getByRole('button', { name: 'Create service' }).click();
        await page.getByRole('button', { name: 'Open check-in' }).click();
        await expect(page.getByText('Check-in is open', { exact: true })).toBeVisible();

        await page.getByRole('link', { name: 'Show QR code' }).click();
        await expect(page.getByRole('img', { name: 'QR code to check in to this service' })).toBeVisible();
        const address =
            (await page
                .getByText(/\/checkin\//)
                .first()
                .textContent()) ?? '';

        // A member's phone is a different browser with no sign-in.
        const phone = await browser.newContext();
        const member = await phone.newPage();
        await member.goto(address.trim());
        await expect(member.getByRole('heading', { level: 1, name: `Check in to ${name}` })).toBeVisible();
        await phone.close();
    });
});

test.describe('weekly schedules', () => {
    test('sets a schedule up, finds its services, and cancels one', async ({ page }) => {
        const stamp = Date.now().toString(36);
        const name = `E2E Weekly ${stamp}`;

        await page.goto(`${CHURCH_URL}/services`);
        await page.getByRole('link', { name: 'Weekly schedules' }).click();
        await expect(page.getByRole('heading', { level: 1, name: 'Weekly schedules' })).toBeVisible();

        await page.getByRole('button', { name: 'New schedule' }).first().click();
        await page.getByLabel(/^Name/).fill(name);
        await page.getByLabel(/^Every/).selectOption('friday');
        await page.getByRole('button', { name: 'Set up schedule' }).click();
        await expect(page.getByText(name).first()).toBeVisible();
        await expect(page.getByText('Every Friday').first()).toBeVisible();

        // Eight services were made at once; open the first one and cancel it.
        await page.goto(`${CHURCH_URL}/services`);
        const first = page.getByRole('link', { name: new RegExp(name) }).first();
        await expect(first).toContainText('Weekly');
        await first.click();
        await expect(page.getByRole('heading', { level: 1, name })).toBeVisible();
        await page.getByRole('button', { name: 'Cancel service' }).click();
        await page.getByRole('alertdialog').getByRole('button', { name: 'Cancel service' }).click();
        await expect(page.getByRole('heading', { level: 1, name: 'Services' })).toBeVisible();
    });
});
