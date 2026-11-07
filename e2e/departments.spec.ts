import { expect, test } from '@playwright/test';
import { CHURCH_URL } from './helpers/config';

test.describe('departments', () => {
    test('the church admin adds a department, sees it as a members filter, is told a name is taken, and retires it', async ({ page }) => {
        // A department is retired, not deleted, so each run leaves one retired department behind.
        const name = `E2E Department ${Date.now().toString(36)}`;

        await page.goto(`${CHURCH_URL}/members`);
        await page.getByRole('link', { name: 'Departments' }).click();
        await expect(page.getByRole('heading', { level: 1, name: 'Departments' })).toBeVisible();

        // Add.
        await page.getByRole('button', { name: 'Add a department' }).click();
        const add = page.getByRole('dialog');
        await add.getByLabel(/^Name/).fill(name);
        await add.getByLabel('What it does').fill('Made by an automated test.');
        await add.getByRole('button', { name: 'Add department' }).click();

        // A toast that names the department is also a list item, so the row is the one with a Change button.
        const row = page
            .getByRole('listitem')
            .filter({ hasText: name })
            .filter({ has: page.getByRole('button', { name: 'Change' }) });
        await expect(row).toBeVisible();
        await expect(row.getByText('In use', { exact: true })).toBeVisible();
        await expect(row.getByText('0 members')).toBeVisible();

        // It is a choice in the members filter.
        await page.goto(`${CHURCH_URL}/members`);
        await expect(page.getByLabel('Department').getByRole('option', { name })).toBeAttached();

        // The same name again is refused beside the box.
        await page.goto(`${CHURCH_URL}/departments`);
        await page.getByRole('button', { name: 'Add a department' }).click();
        const again = page.getByRole('dialog');
        await again.getByLabel(/^Name/).fill(name);
        await again.getByRole('button', { name: 'Add department' }).click();
        await expect(again.getByRole('alert')).toBeVisible();
        await again.getByRole('button', { name: 'Cancel' }).click();

        // Retire it, after being asked.
        await row.getByRole('button', { name: 'Retire' }).click();
        await page.getByRole('alertdialog').getByRole('button', { name: 'Retire' }).click();
        await expect(row.getByText('Retired', { exact: true })).toBeVisible();

        // It is no longer a choice in the members filter.
        await page.goto(`${CHURCH_URL}/members`);
        await expect(page.getByLabel('Department').getByRole('option', { name })).toHaveCount(0);
    });
});
