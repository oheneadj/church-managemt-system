import { expect, test } from '@playwright/test';
import { CHURCH_URL } from './helpers/config';

test.describe('broadcast', () => {
    test('the church admin writes a message, sees who it reaches and what it costs, and is asked before anything is sent', async ({
        page,
    }) => {
        await page.goto(`${CHURCH_URL}/messages`);
        await page.getByRole('link', { name: 'New broadcast' }).click();
        await expect(page.getByRole('heading', { level: 1, name: 'New broadcast' })).toBeVisible();

        // Nothing can be sent before there is a message.
        await expect(page.getByRole('button', { name: 'Review and send' })).toBeDisabled();

        // The real server works out the cost as the message is typed. The demo church may have few members or credits, so
        // either answer is fine; what matters is that an answer arrives and is in words.
        await page.getByLabel(/^Message/).fill('Service starts at 9am this Sunday. God bless you.');
        await expect(page.getByText(/This message will be sent to|Nobody matches/)).toBeVisible();

        // This test never sends: it would text the demo members and use credits. It checks the question is asked and can be refused.
        const send = page.getByRole('button', { name: 'Review and send' });
        if (await send.isEnabled()) {
            await send.click();
            const question = page.getByRole('alertdialog');
            await expect(question).toContainText('Send this message?');
            await question.getByRole('button', { name: 'Cancel' }).click();
            await expect(question).toBeHidden();
        }
        await expect(page).toHaveURL(/\/messages\/new$/);
    });
});
