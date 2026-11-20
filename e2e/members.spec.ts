import { expect, test } from '@playwright/test';
import { CHURCH_URL } from './helpers/config';

test.describe('a church admin managing members', () => {
    test('adds a member in three steps and finds them in the directory', async ({ page }) => {
        const stamp = Date.now().toString(36);
        const lastName = `Tester${stamp}`;
        const phone = `0201${String(Date.now()).slice(-6)}`;

        // Add a member.
        await page.goto(`${CHURCH_URL}/members/new`);
        await page.getByLabel(/First name/).fill('Abena');
        await page.getByLabel(/Last name/).fill(lastName);
        await page.getByLabel(/Gender/).selectOption('female');
        // The date of birth is the browser's own date input, so it takes year-month-day.
        await expect(page.getByLabel(/Date of birth/)).toHaveAttribute('type', 'date');
        await page.getByLabel(/Date of birth/).fill('1985-07-12');
        await page.getByRole('button', { name: 'Next' }).click();
        await expect(page.getByText('Step 2 of 3')).toBeVisible();
        await page.getByLabel(/^Phone number/).fill(phone);
        await page.getByRole('button', { name: 'Next' }).click();
        await expect(page.getByText('Step 3 of 3')).toBeVisible();
        await page.getByRole('button', { name: 'Add member' }).click();

        // The new member's page opens.
        await expect(page.getByRole('heading', { level: 1, name: new RegExp(lastName) })).toBeVisible();

        // The date was saved: the edit form shows it again in the date input.
        await page.getByRole('link', { name: 'Edit' }).click();
        await expect(page.getByLabel(/Date of birth/)).toHaveValue('1985-07-12');

        // They can be found by name and by the phone typed in local form.
        await page.goto(`${CHURCH_URL}/members`);
        await page.getByLabel('Search members').fill(lastName);
        await page.getByRole('button', { name: 'Search' }).click();
        // The directory is a table on a wide screen, where View and Edit buttons are links too, so find the member by the name link itself.
        await expect(page.getByRole('link', { name: `Abena ${lastName}`, exact: true })).toBeVisible();

        await page.getByLabel('Search members').fill(phone);
        await page.getByRole('button', { name: 'Search' }).click();
        await expect(page.getByRole('link', { name: `Abena ${lastName}`, exact: true })).toBeVisible();
    });
});

// This one must not use the saved sign-in.
test.describe('without signing in', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('a visitor who is not signed in is sent to the login page', async ({ page }) => {
        await page.goto(`${CHURCH_URL}/members`);

        await expect(page).toHaveURL(/\/login$/);
    });
});
