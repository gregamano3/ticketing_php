import { test, expect, USERS, asRole, tomSelect, waitForMail, clearMailbox } from './support.js';

/**
 * The core helpdesk journey across three users:
 * requester opens a ticket → agent works it → requester sees the result.
 */
test.describe.serial('ticket journey: requester → agent → requester', () => {
    const subject = `E2E printer jam ${Date.now()}`;
    let reference;

    test.beforeAll(async ({ request }) => {
        await clearMailbox(request);
    });

    test('requester searches the KB, then opens a ticket with an attachment', async ({ browser, request }) => {
        const { page, close } = await asRole(browser, 'requester');

        await page.goto('/kb');
        await page.getByPlaceholder(/search articles/i).fill('printer');
        await page.locator('main').getByRole('button', { name: 'Search' }).click();
        await expect(page.getByText(/result\(s\) for “printer”/)).toBeVisible();
        await expect(page.locator('.card', { hasText: 'result(s) for' }).getByRole('link', { name: /Adding a network printer/ })).toBeVisible();

        await page.getByRole('link', { name: 'New ticket' }).first().click();
        await expect(page.getByRole('heading', { name: 'New ticket' })).toBeVisible();
        // Requesters cannot pick an assignee.
        await expect(page.locator('#assignee_id')).toHaveCount(0);

        await page.getByLabel(/^Subject/).fill(subject);
        await page.getByLabel(/^Description/).fill('The 3rd floor printer shows "Paper jam in tray 2" but there is no paper stuck.');
        await page.getByLabel('Department').selectOption({ label: 'IT Support' });
        // Categories are narrowed to the chosen department.
        await expect(page.locator('#category_id option', { hasText: 'Payroll' })).toBeHidden();
        await page.getByLabel('Category').selectOption({ label: 'Hardware › Printer' });
        await page.getByLabel('Priority').selectOption({ index: 2 }); // High
        await tomSelect(page, '#watchers', 'Ben Cruz');
        await page.getByLabel('Attachments').setInputFiles({ name: 'printer-error.txt', mimeType: 'text/plain', buffer: Buffer.from('E-204 tray 2') });
        await page.getByRole('button', { name: 'Submit ticket' }).click();

        await expect(page.getByText(/Ticket TKT-\d{6} created\./)).toBeVisible();
        reference = page.url().match(/TKT-\d{6}/)[0];
        await expect(page.getByRole('link', { name: 'printer-error.txt' })).toBeVisible();
        await expect(page.locator('.badge', { hasText: 'Open' }).first()).toBeVisible();

        await waitForMail(request, `to:${USERS.requester.email} subject:"${reference}" subject:"We received your request"`);
        await close();
    });

    test('agent claims the ticket, adds an internal note and replies with a canned response', async ({ browser, request }) => {
        const { page, close } = await asRole(browser, 'agent');

        await page.goto('/tickets?view=open');
        await page.getByPlaceholder(/Reference, subject/).fill(reference);
        await page.getByRole('button', { name: 'Filter' }).click();
        await page.getByRole('link', { name: reference }).click();
        await expect(page.getByRole('heading', { name: new RegExp(subject) })).toBeVisible();

        // Auto-assignment may have picked another IT agent; claim it either way.
        const claim = page.getByRole('button', { name: 'Assign to me' });
        if (await claim.isVisible()) {
            await claim.click();
            await expect(page.getByText('You are now assigned to this ticket.')).toBeVisible();
        }

        // Internal note: the switch changes the button, the note is flagged.
        await page.locator('#reply-body').fill('E2E-INTERNAL: firmware on PRN-3F-01 is outdated.');
        await page.getByLabel(/Internal note/).check();
        await expect(page.locator('#reply-submit')).toHaveText(/Add internal note/);
        await page.locator('#reply-submit').click();
        await expect(page.getByText('Internal note added.')).toBeVisible();
        await expect(page.locator('.timeline-internal', { hasText: 'E2E-INTERNAL' })).toBeVisible();

        // Canned reply inserts text with placeholders replaced.
        await page.locator('#canned-picker').selectOption({ label: 'Acknowledge' });
        await expect(page.locator('#reply-body')).toHaveValue(new RegExp(`Hi ${USERS.requester.name},[\\s\\S]*\\(${reference}\\)[\\s\\S]*${USERS.agent.name}`));
        await page.locator('#reply-body').press('End');
        await page.locator('#reply-body').pressSequentially('\nE2E-PUBLIC: firmware updated, please try again.');
        await page.locator('select[name="status_id"]').first().selectOption({ label: 'Set to Resolved' });
        await page.locator('#reply-submit').click();
        await expect(page.getByText('Reply posted.')).toBeVisible();

        // Audit log and SLA panel reflect the work.
        const audit = page.locator('.card', { has: page.getByRole('heading', { name: 'Audit log' }) });
        await expect(audit).toContainText('Status: Open → Resolved');
        await expect(page.locator('.card', { hasText: 'First response' })).not.toContainText('Awaiting');

        await waitForMail(request, `to:${USERS.requester.email} subject:"${reference}" subject:"New reply"`);
        await close();
    });

    test('requester sees the public reply but not the internal note, then reopens by replying', async ({ browser }) => {
        const { page, close } = await asRole(browser, 'requester');

        await page.goto('/home');
        // The navbar bell polls for unread notifications.
        await expect(page.locator('#navbar-notifications .navbar-badge')).toHaveText(/^[1-9]\d*$/);
        await page.locator('#navbar-notifications > a').click();
        await expect(page.locator('#navbar-notifications .dropdown-menu')).toContainText(reference);

        await page.goto(`/tickets/${reference}`);
        await expect(page.getByText('E2E-PUBLIC: firmware updated')).toBeVisible();
        await expect(page.getByText('E2E-INTERNAL')).toHaveCount(0);
        await expect(page.locator('.badge', { hasText: 'Resolved' }).first()).toBeVisible();
        // Requesters get a read-only details card, not the properties form.
        await expect(page.getByRole('heading', { name: 'Properties' })).toHaveCount(0);
        await expect(page.getByLabel(/Internal note/)).toHaveCount(0);

        await expect(page.getByRole('heading', { name: /reopen the ticket/ })).toBeVisible();
        await page.locator('#reply-body').fill('Still jamming, sorry!');
        await page.getByRole('button', { name: 'Send reply' }).click();
        await expect(page.getByText('Reply posted.')).toBeVisible();
        await expect(page.locator('.badge', { hasText: /^Open$/ }).first()).toBeVisible();
        await close();
    });

    test('a different requester cannot open the ticket', async ({ browser }) => {
        const { page, close } = await asRole(browser, 'admin');
        // Admin creates a ticket for themselves; the requester must not see it.
        await page.goto('/tickets/create');
        await page.getByLabel(/^Subject/).fill(`E2E admin-only ${Date.now()}`);
        await page.getByLabel(/^Description/).fill('Private to admin.');
        await page.getByRole('button', { name: 'Submit ticket' }).click();
        const adminRef = page.url().match(/TKT-\d{6}/)[0];
        await close();

        const requester = await asRole(browser, 'requester');
        const res = await requester.page.goto(`/tickets/${adminRef}`);
        expect(res.status()).toBe(403);
        for (const url of ['/admin/users', '/admin/lookups/priorities', '/reports', '/canned-responses']) {
            expect((await requester.page.goto(url)).status(), url).toBe(403);
        }
        await requester.close();
    });
});
