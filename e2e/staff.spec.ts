import { expect, test } from '@playwright/test';
import { CHURCH_URL } from './helpers/config';

test.describe('staff', () => {
    test('the church admin invites someone, changes their role, switches them off and on again', async ({ page }) => {
        const stamp = Date.now().toString(36);
        // "Aaa" sorts first, so the new person is on the first page however many staff earlier runs left behind.
        const name = `Aaa Invitee ${stamp}`;
        const email = `invitee-${stamp}@example.com`;
        const phone = `0203${String(Date.now()).slice(-6)}`;

        await page.goto(`${CHURCH_URL}/dashboard`);
        await page.getByRole('link', { name: 'Staff' }).click();
        await expect(page.getByRole('heading', { level: 1, name: 'Staff' })).toBeVisible();

        // Invite.
        await page.getByRole('button', { name: 'Invite staff' }).click();
        const invite = page.getByRole('dialog');
        await invite.getByLabel(/^Name/).fill(name);
        await invite.getByLabel(/^Email/).fill(email.toUpperCase());
        await invite.getByLabel(/^Phone/).fill(phone);
        await invite.getByLabel(/^Role/).selectOption('pastor');
        await invite.getByRole('button', { name: 'Send invitation' }).click();

        // Exact texts below: a toast that names the person is also a list item, and "Active" is inside "Deactivated".
        const row = page
            .getByRole('listitem')
            .filter({ hasText: name })
            .filter({ has: page.getByRole('button', { name: 'Change' }) });
        await expect(row).toBeVisible();
        await expect(row.getByText(email)).toBeVisible();
        await expect(row.getByText('Pastor', { exact: true })).toBeVisible();
        await expect(row.getByText('Active', { exact: true })).toBeVisible();
        await expect(row.getByText('Never signed in', { exact: true })).toBeVisible();

        // Change the role.
        await row.getByRole('button', { name: 'Change' }).click();
        const change = page.getByRole('dialog');
        await expect(change.getByLabel(/^Role/)).toHaveValue('pastor');
        await change.getByLabel(/^Role/).selectOption('youth-pastor');
        await change.getByRole('button', { name: 'Save changes' }).click();
        await expect(row.getByText('Youth pastor', { exact: true })).toBeVisible();

        // Switch off (after being asked), then on.
        await row.getByRole('button', { name: 'Deactivate' }).click();
        await page.getByRole('alertdialog').getByRole('button', { name: 'Deactivate' }).click();
        await expect(row.getByText('Deactivated', { exact: true })).toBeVisible();
        await row.getByRole('button', { name: 'Reactivate' }).click();
        await expect(row.getByText('Active', { exact: true })).toBeVisible();
    });

    test('the same email cannot be invited twice, and the message is beside the box', async ({ page }) => {
        await page.goto(`${CHURCH_URL}/staff`);
        await page.getByRole('button', { name: 'Invite staff' }).click();
        const invite = page.getByRole('dialog');
        await invite.getByLabel(/^Name/).fill('Duplicate Person');
        await invite.getByLabel(/^Email/).fill('admin@demo.church.test');
        await invite.getByLabel(/^Phone/).fill(`0204${String(Date.now()).slice(-6)}`);
        await invite.getByLabel(/^Role/).selectOption('pastor');
        await invite.getByRole('button', { name: 'Send invitation' }).click();

        await expect(invite.getByText('A staff member with this email already exists.')).toBeVisible();
        await expect(invite).toBeVisible();
    });
});
