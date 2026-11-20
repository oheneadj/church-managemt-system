import { expect, test } from '@playwright/test';
import { CHURCH_URL } from './helpers/config';

test.describe('settings', () => {
    test('the church admin changes the details and the preferences, and they are still there after a reload', async ({ page }) => {
        const denomination = `Denomination ${Date.now().toString(36)}`;

        await page.goto(`${CHURCH_URL}/dashboard`);
        await page.getByRole('link', { name: 'Settings' }).click();
        await expect(page.getByRole('heading', { level: 1, name: 'Settings' })).toBeVisible();

        // Change one detail and switch the absence text on.
        await page.getByLabel('Denomination').fill(denomination);
        await page.getByRole('button', { name: 'Save details' }).click();
        await expect(page.getByText('Church details saved.')).toBeVisible();

        const absence = page.getByLabel('Text members who have gone absent');
        const wasOn = await absence.isChecked();
        await absence.setChecked(!wasOn);
        await page.getByRole('button', { name: 'Save preferences' }).click();
        await expect(page.getByText('Settings saved.')).toBeVisible();

        // Still there after a reload.
        await page.reload();
        await expect(page.getByLabel('Denomination')).toHaveValue(denomination);
        await expect(page.getByLabel('Text members who have gone absent')).toBeChecked({ checked: !wasOn });

        // Put the switch back, so the demo church does not start texting people.
        await page.getByLabel('Text members who have gone absent').setChecked(wasOn);
        await page.getByRole('button', { name: 'Save preferences' }).click();
        await expect(page.getByText('Settings saved.')).toBeVisible();
    });

    test('a number outside its range is refused beside the box', async ({ page }) => {
        await page.goto(`${CHURCH_URL}/settings`);

        await page.getByLabel(/A member is absent after missing/).fill('20');
        await page.getByRole('button', { name: 'Save preferences' }).click();

        await expect(page.getByText('The number of services must be a whole number from 2 to 12')).toBeVisible();
    });
});
