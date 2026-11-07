import { expect, test } from '@playwright/test';
import { CHURCH_URL, DEMO_ADMIN_EMAIL } from './helpers/config';

// These only ask the question and cancel. Really asking would use one of the five exports an hour that a staff member is
// allowed (and email a file of the demo members), so the full journey (click, email, signed link, CSV) is checked by hand.
test.describe('exports', () => {
    test('the member list asks before exporting, and says where the link goes and how long it works', async ({ page }) => {
        await page.goto(`${CHURCH_URL}/members`);
        await page.getByRole('button', { name: 'Export list' }).click();

        const question = page.getByRole('alertdialog');
        await expect(question).toContainText('Export list?');
        await expect(question).toContainText(DEMO_ADMIN_EMAIL);
        await expect(question).toContainText('works for 1 hour');
        await expect(question).toContainText('deleted after 7 days');

        await question.getByRole('button', { name: 'Cancel' }).click();
        await expect(question).toBeHidden();
    });

    test('the gift list asks before exporting, and says voided gifts are included', async ({ page }) => {
        await page.goto(`${CHURCH_URL}/giving`);
        await page.getByRole('button', { name: 'Export gifts' }).click();

        const question = page.getByRole('alertdialog');
        await expect(question).toContainText('Voided gifts are included and marked');
        await expect(question).toContainText(DEMO_ADMIN_EMAIL);

        await question.getByRole('button', { name: 'Cancel' }).click();
        await expect(question).toBeHidden();
    });
});
