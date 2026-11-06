import { expect, test as setup } from '@playwright/test';
import { CHURCH_URL, DEMO_ADMIN_EMAIL, DEMO_ADMIN_PASSWORD } from './helpers/config';
import { emailedCodeFor } from './helpers/signInCode';

/** Where the signed-in browser state is kept for the other specs, so a run asks for one sign-in code and not one per spec. */
export const ADMIN_STATE = 'e2e/.auth/admin.json';

setup('an admin signs in with email, password and an emailed code', async ({ page }) => {
    await page.goto(`${CHURCH_URL}/login`);
    await expect(page.getByRole('heading', { level: 1, name: 'Sign in' })).toBeVisible();
    await page.getByLabel(/Email address/).fill(DEMO_ADMIN_EMAIL);
    await page.getByLabel(/Password/).fill(DEMO_ADMIN_PASSWORD);

    const before = new Date();
    await page.getByRole('button', { name: 'Continue' }).click();
    await page.getByLabel(/Code/).fill(await emailedCodeFor(DEMO_ADMIN_EMAIL, before));
    await page.getByRole('button', { name: 'Sign in' }).click();

    await expect(page).toHaveURL(/\/dashboard$/);
    await page.context().storageState({ path: ADMIN_STATE });
});
