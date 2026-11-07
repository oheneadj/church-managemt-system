import { expect, test } from '@playwright/test';
import { CHURCH_URL } from './helpers/config';

test.describe('reports', () => {
    test('the church admin opens the attendance report, and changes it, with the choices kept in the address', async ({ page }) => {
        await page.goto(`${CHURCH_URL}/dashboard`);
        await page.getByRole('link', { name: 'Reports' }).click();
        await expect(page.getByRole('heading', { level: 1, name: 'Reports' })).toBeVisible();

        // The demo church may have recorded little attendance: either the figures or the plain empty message, never an error.
        await expect(page.getByText(/^Average$|No attendance recorded/)).toBeVisible();
        await expect(page.getByRole('alert')).toHaveCount(0);

        await page.getByLabel('Show').selectOption('26');
        await page.getByLabel('Group').selectOption('service');
        await expect(page).toHaveURL(/weeks=26/);
        await expect(page).toHaveURL(/group=service/);

        await page.reload();
        await expect(page.getByLabel('Show')).toHaveValue('26');
        await expect(page.getByLabel('Group')).toHaveValue('service');
        await expect(page.getByRole('alert')).toHaveCount(0);
    });
});
