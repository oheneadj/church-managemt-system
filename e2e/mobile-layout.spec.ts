import { expect, test } from '@playwright/test';
import { CHURCH_URL } from './helpers/config';

/** The narrowest screen the app supports: an iPhone SE (docs/10, Definition of Done). */
const PHONE = { width: 375, height: 667 };

const PAGES = ['/dashboard', '/members', '/families', '/services', '/giving', '/pledges', '/settings', '/messages'];

test.describe('on a 375px phone', () => {
    test.use({ viewport: PHONE });

    for (const path of PAGES) {
        test(`${path} does not scroll sideways and keeps every menu link on screen`, async ({ page }) => {
            await page.goto(`${CHURCH_URL}${path}`);
            await expect(page.getByRole('navigation', { name: 'Main' })).toBeVisible();

            const sideways = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            expect(sideways, 'the page is wider than the screen').toBeLessThanOrEqual(0);

            // Every menu link sits fully inside the screen, so none is cut off.
            for (const link of await page.getByRole('navigation', { name: 'Main' }).getByRole('link').all()) {
                const box = await link.boundingBox();
                expect(box, 'a menu link has a position').not.toBeNull();
                expect((box?.x ?? 0) + (box?.width ?? 0)).toBeLessThanOrEqual(PHONE.width);
            }
        });
    }
});
