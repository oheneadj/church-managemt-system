import { expect, test } from '@playwright/test';
import { PLATFORM_URL } from './helpers/config';
import { codeWrittenAfter, logPosition } from './helpers/signInCode';

/** A phone number that no earlier run has used, so the one-code-per-minute limit never blocks a rerun. */
function freshPhone(): string {
    return `0200${String(Date.now()).slice(-6)}`;
}

test.describe('starting a church', () => {
    test('a new church signs up with a texted code and gets its own address', async ({ page }) => {
        const stamp = Date.now().toString(36);
        const phone = freshPhone();

        await page.goto(`${PLATFORM_URL}/start`);
        await expect(page.getByRole('heading', { name: 'Start your church' })).toBeVisible();

        await page.getByLabel(/Church name/).fill(`E2E Church ${stamp}`);
        await page.getByLabel(/Member number letters/).fill('eee');
        await page.getByLabel(/Your name/).fill('E2E Admin');
        await page.getByLabel(/Your phone number/).fill(phone);

        const before = logPosition();
        await page.getByRole('button', { name: 'Text me a code' }).click();
        await page.getByLabel(/Code from the text message/).fill(await codeWrittenAfter(before));
        await page.getByRole('button', { name: 'Start my church' }).click();

        const link = page.getByRole('link', { name: 'Go to my church' });
        await expect(link).toBeVisible();
        await expect(link).toHaveAttribute('href', new RegExp(`e2e-church-${stamp}\\..*/login$`));
        await expect(page.getByText(/second admin/)).toBeVisible();
    });

    test('mistakes are refused beside the box they belong to, and the same code still works afterwards', async ({ page }) => {
        const phone = freshPhone();

        await page.goto(`${PLATFORM_URL}/start`);
        await page.getByLabel(/Church name/).fill('Mistakes Church');
        await page.getByLabel(/Web address/).fill('admin');
        await page.getByLabel(/Member number letters/).fill('mis');
        await page.getByLabel(/Your name/).fill('E2E Admin');
        await page.getByLabel(/Your phone number/).fill(phone);

        const before = logPosition();
        await page.getByRole('button', { name: 'Text me a code' }).click();
        const code = await codeWrittenAfter(before);
        const wrong = code === '000000' ? '111111' : '000000';
        const codeBox = page.getByLabel(/Code from the text message/);
        const start = page.getByRole('button', { name: 'Start my church' });

        // A web address kept for the platform is refused beside the address box.
        await codeBox.fill(code);
        await start.click();
        await expect(page.getByText('This web address is kept for the platform. Please choose another.')).toBeVisible();

        // With a good address, a wrong code is refused beside the code box.
        await page.getByLabel(/Web address/).fill('');
        await codeBox.fill(wrong);
        await start.click();
        await expect(page.getByText('Invalid code')).toBeVisible();
        await expect(page.getByRole('link', { name: 'Go to my church' })).toHaveCount(0);
    });
});
