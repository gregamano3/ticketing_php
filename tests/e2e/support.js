import { test as base, expect } from '@playwright/test';

export const MAILPIT = process.env.E2E_MAILPIT_URL ?? 'http://localhost:8025';
export const PASSWORD = 'password'; // demo seed password (database/seeders/DemoSeeder.php)

export const USERS = {
    admin: { email: 'admin@example.com', name: 'Admin User' },
    agent: { email: 'agent@example.com', name: 'Alice Santos' },
    requester: { email: 'user@example.com', name: 'Rachel Requester' },
};

export const authFile = (role) => `storage/e2e/.auth/${role}.json`;

const IGNORED_CONSOLE = /Failed to load resource: the server responded with a status of (403|404)/;

/** Collect uncaught page errors and console errors from a page. */
export function collectErrors(page) {
    const errors = [];
    page.on('pageerror', (err) => errors.push(`pageerror: ${err.message}`));
    page.on('console', (msg) => {
        // A 403/404 page logs the failed document request; tests assert those statuses explicitly.
        if (msg.type() === 'error' && !IGNORED_CONSOLE.test(msg.text())) errors.push(`console: ${msg.text()}`);
    });
    return errors;
}

/**
 * Test fixture that fails the test on any uncaught page error or console
 * error, so broken JavaScript (Tom Select, Quill, Chart.js, ...) is caught.
 */
export const test = base.extend({
    page: async ({ page }, use) => {
        const errors = collectErrors(page);
        await use(page);
        expect(errors, 'browser console should be free of errors').toEqual([]);
    },
});

export { expect };

/**
 * Open a page as a given role in a fresh, isolated context. close() asserts
 * the page logged no browser errors, then closes the context.
 */
export async function asRole(browser, role) {
    const context = await browser.newContext({ storageState: authFile(role) });
    const page = await context.newPage();
    const errors = collectErrors(page);

    return {
        context,
        page,
        close: async () => {
            await context.close();
            expect(errors, `browser console (${role}) should be free of errors`).toEqual([]);
        },
    };
}

/** Pick an option in a Tom Select widget wrapping the given native <select>. */
export async function tomSelect(page, selectSelector, optionText) {
    const wrapper = page.locator(`${selectSelector} + .ts-wrapper`);
    // Click the text input: clicking the control itself may hit an existing item chip.
    await wrapper.locator('.ts-control input').click();
    await wrapper.locator('.ts-control input').fill(optionText);
    await page.locator('.ts-dropdown .option', { hasText: optionText }).first().click();
    await page.keyboard.press('Escape');
}

/** Wait until Mailpit has a message matching the query and return it. */
export async function waitForMail(request, query) {
    let found;
    await expect.poll(async () => {
        const res = await request.get(`${MAILPIT}/api/v1/search`, { params: { query } });
        found = (await res.json()).messages?.[0];
        return !!found;
    }, { message: `email matching "${query}"`, timeout: 20_000 }).toBe(true);
    return found;
}

export async function clearMailbox(request) {
    await request.delete(`${MAILPIT}/api/v1/messages`);
}
