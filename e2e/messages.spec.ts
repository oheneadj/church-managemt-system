import { expect, test } from '@playwright/test';
import { CHURCH_URL } from './helpers/config';

test.describe('messages', () => {
    test('the church admin opens the message log and narrows it, and the filters are in the address', async ({ page }) => {
        await page.goto(`${CHURCH_URL}/dashboard`);
        await page.getByRole('link', { name: 'Messages' }).click();
        await expect(page.getByRole('heading', { level: 1, name: 'Messages' })).toBeVisible();

        // Either messages or the empty state: the demo church may have sent none. Never an error.
        await expect(page.getByRole('alert')).toHaveCount(0);

        await page.getByLabel('Sent by').selectOption('sms');
        await page.getByLabel('Kind').selectOption('broadcast');
        await expect(page).toHaveURL(/channel=sms/);
        await expect(page).toHaveURL(/type=broadcast/);

        await page.reload();
        await expect(page.getByLabel('Sent by')).toHaveValue('sms');
        await expect(page.getByLabel('Kind')).toHaveValue('broadcast');

        await page.getByRole('button', { name: 'Clear filters' }).click();
        await expect(page).not.toHaveURL(/channel=/);
        await expect(page.getByLabel('Sent by')).toHaveValue('');
    });
});
