import { test as setup, expect } from '@playwright/test';
import { USERS, PASSWORD, authFile } from './support.js';

for (const [role, user] of Object.entries(USERS)) {
    setup(`log in as ${role}`, async ({ page }) => {
        await page.goto('/login');
        await page.getByPlaceholder(/email/i).fill(user.email);
        await page.getByPlaceholder(/password/i).fill(PASSWORD);
        await page.getByRole('button', { name: /sign in|log in|login/i }).click();

        await expect(page).toHaveURL(/\/home$/);
        await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
        await page.context().storageState({ path: authFile(role) });
    });
}
