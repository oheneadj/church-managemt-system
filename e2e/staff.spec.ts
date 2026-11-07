import { expect, test } from '@playwright/test';
import { CHURCH_URL } from './helpers/config';
import { emailedCodeFor } from './helpers/signInCode';

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

    test('an invited person chooses their password with the emailed code, then signs in', async ({ page, browser }) => {
        const stamp = Date.now().toString(36);
        const email = `chooser-${stamp}@example.com`;
        const password = 'an invited e2e passphrase';

        // The church admin invites them.
        const sent = new Date();
        await page.goto(`${CHURCH_URL}/staff`);
        await page.getByRole('button', { name: 'Invite staff' }).click();
        const invite = page.getByRole('dialog');
        await invite.getByLabel(/^Name/).fill(`Aab Chooser ${stamp}`);
        await invite.getByLabel(/^Email/).fill(email);
        await invite.getByLabel(/^Phone/).fill(`0205${String(Date.now()).slice(-6)}`);
        await invite.getByLabel(/^Role/).selectOption('pastor');
        await invite.getByRole('button', { name: 'Send invitation' }).click();
        await expect(page.getByRole('dialog')).toHaveCount(0);

        // The invited person, signed out, uses the code from the email to choose a password.
        const guest = await browser.newContext({ storageState: { cookies: [], origins: [] } });
        const visitor = await guest.newPage();
        await visitor.goto(`${CHURCH_URL}/login`);
        await visitor.getByRole('link', { name: 'Have an invitation code?' }).click();
        await expect(visitor.getByRole('heading', { level: 1, name: 'Choose your password' })).toBeVisible();
        await visitor.getByLabel(/^Email address/).fill(email);
        await visitor.getByLabel(/^Code/).fill(await emailedCodeFor(email, sent));
        await visitor.getByLabel(/^New password/).fill(password);
        await visitor.getByLabel(/Type the new password again/).fill(password);
        await visitor.getByRole('button', { name: 'Save password' }).click();
        await expect(visitor.getByRole('heading', { name: 'Password saved' })).toBeVisible();

        // They can now sign in the normal way.
        await visitor.getByRole('link', { name: 'Go to sign in' }).click();
        await visitor.getByLabel(/Email address/).fill(email);
        await visitor.getByLabel(/Password/).fill(password);
        const asked = new Date();
        await visitor.getByRole('button', { name: 'Continue' }).click();
        await visitor.getByLabel(/Code/).fill(await emailedCodeFor(email, asked));
        await visitor.getByRole('button', { name: 'Sign in' }).click();
        await expect(visitor).toHaveURL(/\/dashboard$/);

        await guest.close();
    });

    test('the church admin gives someone an extra permission and takes it away again, and the role permissions cannot be changed', async ({
        page,
    }) => {
        const stamp = Date.now().toString(36);
        const name = `Aab Permissions ${stamp}`;

        await page.goto(`${CHURCH_URL}/staff`);
        await page.getByRole('button', { name: 'Invite staff' }).click();
        const invite = page.getByRole('dialog');
        await invite.getByLabel(/^Name/).fill(name);
        await invite.getByLabel(/^Email/).fill(`perm-${stamp}@example.com`);
        await invite.getByLabel(/^Phone/).fill(`0205${String(Date.now()).slice(-6)}`);
        await invite.getByLabel(/^Role/).selectOption('pastor');
        await invite.getByRole('button', { name: 'Send invitation' }).click();

        // A toast that names the person is also a list item, so the row is the one with a Change button.
        const row = page
            .getByRole('listitem')
            .filter({ hasText: name })
            .filter({ has: page.getByRole('button', { name: 'Change' }) });
        await expect(row).toBeVisible();

        // What the role gives is ticked and fixed; the rest can be ticked.
        await row.getByRole('button', { name: 'Permissions' }).click();
        const permissions = page.getByRole('dialog');
        await expect(permissions.getByLabel(/View members/)).toBeChecked();
        await expect(permissions.getByLabel(/View members/)).toBeDisabled();
        await expect(permissions.getByRole('button', { name: 'Save permissions' })).toBeDisabled();

        await permissions.getByLabel('Export the member list').check();
        await permissions.getByRole('button', { name: 'Save permissions' }).click();
        await expect(row.getByText('Also allowed: Export the member list')).toBeVisible();

        // It is still ticked when opened again, and can be taken away.
        await row.getByRole('button', { name: 'Permissions' }).click();
        await expect(page.getByRole('dialog').getByLabel('Export the member list')).toBeChecked();
        await page.getByRole('dialog').getByLabel('Export the member list').uncheck();
        await page.getByRole('dialog').getByRole('button', { name: 'Save permissions' }).click();
        await expect(row.getByText(/Also allowed/)).toHaveCount(0);
    });
});
