import { expect, test as setup } from '@playwright/test';
import { CHURCH_URL, DEMO_ADMIN_PHONE } from './helpers/config';
import { codeWrittenAfter, logPosition } from './helpers/signInCode';

/** Where the signed-in browser state is kept for the other specs, so a run asks for one sign-in code and not one per spec. */
export const ADMIN_STATE = 'e2e/.auth/admin.json';

setup('an admin signs in with a texted code', async ({ page }) => {
    await page.goto(`${CHURCH_URL}/login`);
    await expect(page.getByRole('heading', { level: 1, name: 'Sign in' })).toBeVisible();
    await page.getByLabel('Phone number').fill(DEMO_ADMIN_PHONE);

    const before = logPosition();
    await page.getByRole('button', { name: 'Send code' }).click();
    await page.getByLabel('Code').fill(await codeWrittenAfter(before));
    await page.getByRole('button', { name: 'Sign in' }).click();

    await expect(page).toHaveURL(/\/dashboard$/);
    await page.context().storageState({ path: ADMIN_STATE });
});
