import { test, expect, authFile } from './support.js';

test.describe('admin', () => {
    test.use({ storageState: authFile('admin') });

    test('dashboard renders stats and both charts', async ({ page }) => {
        await page.goto('/home');
        await expect(page.locator('.small-box')).toHaveCount(4);
        await expect(page.locator('.small-box', { hasText: 'Open tickets' }).locator('h3')).toHaveText(/^\d+$/);
        await expect.poll(() => page.evaluate(() => ['trendChart', 'statusChart'].every((id) => !!window.Chart?.getChart(id)))).toBe(true);
        await expect(page.getByRole('heading', { name: /Recent activity/ })).toBeVisible();
    });

    test('sidebar shows every section for admins and dark mode toggles', async ({ page }) => {
        await page.goto('/home');
        const sidebar = page.locator('.app-sidebar');
        for (const item of ['Open tickets', 'Overdue', 'Knowledge base', 'Canned replies', 'Reports', 'Users', 'Configuration']) {
            await expect(sidebar.getByText(item, { exact: true })).toBeVisible();
        }
        await page.locator('#adminlte-color-mode').click();
        await page.locator('[data-bs-theme-value="dark"]').click();
        await expect(page.locator('html')).toHaveAttribute('data-bs-theme', 'dark');
        await page.reload();
        await expect(page.locator('html')).toHaveAttribute('data-bs-theme', 'dark'); // remembered
    });

    test('ticket list filters, sorts and exports CSV', async ({ page }) => {
        await page.goto('/tickets?view=all');
        await expect(page.locator('table tbody tr')).not.toHaveCount(0);

        await page.locator('select[name="priority"]').selectOption({ label: 'Urgent' });
        await page.getByRole('button', { name: 'Filter' }).click();
        const priorities = await page.locator('table tbody tr td:nth-child(4)').allInnerTexts();
        expect(priorities.length).toBeGreaterThan(0);
        expect(priorities.every((p) => p.includes('Urgent'))).toBe(true);

        await page.getByRole('link', { name: /^Priority/ }).click();
        await expect(page).toHaveURL(/sort=priority/);

        const download = page.waitForEvent('download');
        await page.getByRole('link', { name: 'Export CSV' }).click();
        const file = await download;
        expect(file.suggestedFilename()).toMatch(/^tickets-.*\.csv$/);
    });

    test('properties panel updates tags with Tom Select', async ({ page }) => {
        await page.goto('/tickets?view=open');
        await page.locator('table tbody tr td:first-child a').first().click();
        const panel = page.locator('.card', { has: page.getByRole('heading', { name: 'Properties' }) });

        // Pick whichever tag the ticket does not have yet (keeps the test repeatable).
        const tags = page.locator('select[name="tags[]"] + .ts-wrapper');
        await tags.locator('.ts-control input').click();
        const option = page.locator('.ts-dropdown .option').first();
        const tag = (await option.innerText()).trim();
        await option.click();
        await page.keyboard.press('Escape');
        await panel.getByRole('button', { name: 'Update' }).click();

        await expect(page.getByText('Ticket updated.')).toBeVisible();
        await expect(page.locator('.card-body .badge', { hasText: tag }).first()).toBeVisible();
    });

    test('reports render charts and agent performance', async ({ page }) => {
        await page.goto('/reports');
        await expect(page.getByRole('heading', { name: 'Reports' })).toBeVisible();
        await expect.poll(() => page.evaluate(() => [...document.querySelectorAll('canvas.report-chart')].filter((c) => window.Chart?.getChart(c)).length)).toBeGreaterThanOrEqual(2);
        await expect(page.locator('table', { hasText: 'Avg first response' })).toContainText('Alice Santos');
    });

    test('admin creates a user and a priority', async ({ page }) => {
        const email = `e2e.${Date.now()}@example.com`;
        await page.goto('/admin/users/create');
        await page.locator('input[name="name"]').fill('E2E Agent');
        await page.locator('input[name="email"]').fill(email);
        await page.locator('select[name="role"]').selectOption('agent');
        await page.locator('select[name="department_id"]').selectOption({ label: 'Facilities' });
        await page.locator('input[name="password"]').fill('Sup3r-secret-pass');
        await page.locator('input[name="password_confirmation"]').fill('Sup3r-secret-pass');
        await page.getByRole('button', { name: 'Save' }).click();
        await expect(page.getByText('User E2E Agent created.')).toBeVisible();
        await expect(page.locator('tr', { hasText: email })).toContainText('Agent');

        await page.goto('/admin/lookups/priorities/create');
        await page.getByLabel('Name').fill(`E2E-P${Date.now() % 10000}`);
        await page.getByLabel('Level').fill('9');
        await page.getByLabel('Color', { exact: true }).selectOption('dark');
        await page.getByLabel(/First response target/).fill('30');
        await page.getByLabel(/Resolution target/).fill('10'); // invalid: below response target
        await page.getByRole('button', { name: 'Save' }).click();
        await expect(page.locator('.invalid-feedback', { hasText: /resolution minutes/i })).toBeVisible();
        await page.getByLabel(/Resolution target/).fill('120');
        await page.getByRole('button', { name: 'Save' }).click();
        await expect(page.getByText('Priority created.')).toBeVisible();
    });
});

test.describe('agent', () => {
    test.use({ storageState: authFile('agent') });

    test('writes a knowledge base article with the Quill editor', async ({ page }) => {
        const title = `E2E Quill article ${Date.now()}`;
        await page.goto('/kb/articles/create');
        await page.getByLabel('Title').fill(title);
        await page.getByLabel('Summary').fill('Written by Playwright');
        await page.getByLabel('Category').selectOption({ label: 'Hardware' });

        const editor = page.locator('.ql-editor');
        await editor.click();
        await page.locator('.ql-toolbar .ql-bold').click();
        await editor.pressSequentially('Bold intro');
        await page.locator('.ql-toolbar .ql-bold').click();
        await editor.press('Enter');
        await editor.pressSequentially('Plain paragraph.');
        await page.getByRole('button', { name: 'Save' }).click();

        await expect(page.getByText('Article created.')).toBeVisible();
        await expect(page.getByRole('heading', { name: title })).toBeVisible();
        await expect(page.locator('.kb-body strong', { hasText: 'Bold intro' })).toBeVisible();

        await page.getByRole('button', { name: /Yes \(\d+\)/ }).click();
        await expect(page.getByText('Thanks for your feedback!')).toBeVisible();
    });

    test('manages a personal canned reply', async ({ page }) => {
        await page.goto('/canned-responses/create');
        await page.getByLabel('Title').fill('E2E personal reply');
        await page.getByLabel('Body').fill('Hello {requester}, from {agent}.');
        // Agents cannot share replies.
        await expect(page.getByLabel(/Shared with all agents/)).toHaveCount(0);
        await page.getByRole('button', { name: 'Save' }).click();
        await expect(page.locator('tr', { hasText: 'E2E personal reply' })).toContainText('Personal');
    });

    test('sidebar hides admin sections and admin pages are forbidden', async ({ page }) => {
        await page.goto('/home');
        await expect(page.locator('.app-sidebar').getByText('Users', { exact: true })).toHaveCount(0);
        expect((await page.goto('/admin/users')).status()).toBe(403);
    });

    test('updates their profile', async ({ page }) => {
        await page.goto('/profile');
        await page.locator('input[name="job_title"]').fill('Senior IT Support Engineer');
        await page.getByRole('button', { name: 'Save' }).click();
        await expect(page.getByText('Profile updated.')).toBeVisible();
        await expect(page.locator('input[name="job_title"]')).toHaveValue('Senior IT Support Engineer');
    });
});

test.describe('requester', () => {
    test.use({ storageState: authFile('requester') });

    test('sees a simplified dashboard and menu', async ({ page }) => {
        await page.goto('/home');
        await expect(page.locator('.small-box', { hasText: 'My open requests' })).toBeVisible();
        const sidebar = page.locator('.app-sidebar');
        await expect(sidebar.getByText('My requests', { exact: true })).toBeVisible();
        for (const hidden of ['Open tickets', 'Reports', 'Canned replies', 'Users']) {
            await expect(sidebar.getByText(hidden, { exact: true })).toHaveCount(0);
        }
    });

    test('cannot see draft KB articles', async ({ page }) => {
        await page.goto('/kb');
        await expect(page.getByText('Drafts')).toHaveCount(0);
        await page.getByPlaceholder(/search articles/i).fill('laptop refresh');
        await page.locator('main').getByRole('button', { name: 'Search' }).click();
        await expect(page.getByText(/Draft: new laptop refresh/)).toHaveCount(0);
    });
});

test('guest is redirected to login and bad credentials are rejected', async ({ page }) => {
    await page.goto('/tickets');
    await expect(page).toHaveURL(/\/login$/);
    await page.getByPlaceholder(/email/i).fill('user@example.com');
    await page.getByPlaceholder(/password/i).fill('wrong-password');
    await page.getByRole('button', { name: /sign in/i }).click();
    await expect(page.locator('.invalid-feedback, .alert-danger')).toContainText(/credentials/i);
});
