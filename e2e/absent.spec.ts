import { expect, test } from '@playwright/test';
import { CHURCH_URL } from './helpers/config';

test.describe('absent members', () => {
    test('the church admin reaches the list from the dashboard and changes how many services count, kept in the address', async ({
        page,
    }) => {
        await page.goto(`${CHURCH_URL}/dashboard`);
        await page.getByRole('link', { name: /Absent members/ }).click();
        await expect(page.getByRole('heading', { level: 1, name: 'Absent members' })).toBeVisible();

        // The demo church may have recorded few Sunday services, so the list can be empty. Either way, never an error.
        await expect(page.getByText(/found$/)).toBeVisible();
        await expect(page.getByRole('alert')).toHaveCount(0);

        await page.getByLabel('Missed').selectOption('5');
        await expect(page).toHaveURL(/consecutive=5/);

        await page.reload();
        await expect(page.getByLabel('Missed')).toHaveValue('5');
        await expect(page.getByRole('alert')).toHaveCount(0);
    });

    test('the attendance report links to the list', async ({ page }) => {
        await page.goto(`${CHURCH_URL}/reports`);
        await page.getByRole('link', { name: 'See who has gone absent' }).click();

        await expect(page).toHaveURL(/\/attendance\/absent$/);
    });
});
