import { test, expect, authFile } from './support.js';

/**
 * Regenerates the README screenshots. Excluded from normal runs; use:
 *   SCREENSHOTS=1 bin/e2e -g @screenshots
 */
test.describe('README screenshots @screenshots', () => {
    test.use({ viewport: { width: 1440, height: 900 } });

    const shot = async (page, name) => {
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: `docs/screenshots/${name}.png` });
    };

    test.describe('as agent', () => {
        test.use({ storageState: authFile('agent') });

        test('dashboard', async ({ page }) => {
            await page.goto('/home');
            await expect.poll(() => page.evaluate(() => !!window.Chart?.getChart('trendChart'))).toBe(true);
            await page.waitForTimeout(800); // let chart animations finish
            await shot(page, 'dashboard');
        });

        test('ticket list', async ({ page }) => {
            await page.goto('/tickets?view=all');
            await shot(page, 'tickets');
        });

        test('triage', async ({ page }) => {
            await page.goto('/tickets?view=triage');
            await page.locator('table tbody tr td:first-child a').first().click();
            await expect(page.locator('#triage-card')).toBeVisible();
            await shot(page, 'triage');
        });

        test('ticket conversation', async ({ page }) => {
            await page.goto('/tickets?view=all&sort=updated_at');
            const withReplies = page.locator('tr', { has: page.locator('.bi-chat') }).filter({ hasNotText: '· 0' }).first();
            await withReplies.locator('td:first-child a').click();
            await shot(page, 'ticket');
        });
    });

    test.describe('as admin', () => {
        test.use({ storageState: authFile('admin') });

        test('reports', async ({ page }) => {
            await page.goto('/reports?from=2000-01-01');
            await page.waitForTimeout(800);
            await shot(page, 'reports');
        });
    });
});
