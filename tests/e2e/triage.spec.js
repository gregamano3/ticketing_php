import { test, expect, asRole, waitForMail } from './support.js';

/**
 * Triage flow: a requester raises an unrouted ticket, the first-line triager
 * (Alice, seeded with tickets.triage) routes it, an admin sends it back.
 */
test.describe.serial('triage', () => {
    const subject = `E2E unrouted request ${Date.now()}`;
    let reference;

    test('requester raises a ticket without choosing a department', async ({ browser }) => {
        const { page, close } = await asRole(browser, 'requester');

        await page.goto('/tickets/create');
        await page.getByLabel(/^Subject/).fill(subject);
        await page.getByLabel(/^Description/).fill('My payslip for September is missing overtime hours.');
        await page.getByLabel('Who is affected?').selectOption({ label: 'Whole department or company' });
        await page.getByLabel('How urgent is it?').selectOption({ label: 'High — I am blocked' });
        await page.getByRole('button', { name: 'Submit ticket' }).click();

        await expect(page.getByText(/Ticket TKT-\d{6} created\./)).toBeVisible();
        reference = page.url().match(/TKT-\d{6}/)[0];
        await expect(page.locator('.badge', { hasText: 'Needs triage' }).first()).toBeVisible();
        await expect(page.locator('.badge', { hasText: 'Urgent' }).first()).toBeVisible(); // suggested from impact × urgency
        await expect(page.locator('#triage-card')).toHaveCount(0);
        await close();
    });

    test('triager finds it in the queue and routes it', async ({ browser, request }) => {
        const { page, close } = await asRole(browser, 'agent');

        await page.goto('/home');
        await expect(page.locator('.small-box', { hasText: 'Needs triage' }).locator('h3')).toHaveText(/^[1-9]\d*$/);
        await page.locator('.app-sidebar').getByText('Needs triage', { exact: true }).click();
        await expect(page).toHaveURL(/view=triage/);
        await page.getByRole('link', { name: reference }).click();

        const card = page.locator('#triage-card');
        await expect(card).toBeVisible();
        await expect(card).toContainText('Suggested from impact/urgency: Urgent');

        await card.getByLabel('Department').selectOption({ label: 'Human Resources' });
        // Categories and assignees narrow to the chosen department.
        await expect(card.locator('#triage_category option', { hasText: /^Hardware$/ })).toBeHidden();
        await expect(card.locator('#triage_assignee option', { hasText: 'Ben Cruz' })).toBeHidden();
        await card.getByLabel('Category').selectOption({ label: 'Payroll' });
        await card.getByLabel('Priority').selectOption({ label: 'High' }); // triager overrides the suggestion
        await card.getByRole('button', { name: 'Complete triage' }).click();

        await expect(page.getByText('Ticket triaged and assigned to Carla Reyes.')).toBeVisible();
        await expect(page.locator('#triage-card')).toHaveCount(0);
        await expect(page.locator('.badge', { hasText: 'Needs triage' })).toHaveCount(0);
        await expect(page.locator('.card', { has: page.getByRole('heading', { name: 'Audit log' }) })).toContainText('triaged the ticket');

        await page.goto('/tickets?view=triage');
        await expect(page.getByRole('link', { name: reference })).toHaveCount(0);

        await waitForMail(request, `to:carla.reyes@example.com subject:"${reference}" subject:"Ticket assigned to you"`);
        await close();
    });

    test('admin sends it back to triage with a reason', async ({ browser }) => {
        const { page, close } = await asRole(browser, 'admin');

        await page.goto(`/tickets/${reference}`);
        await page.getByRole('button', { name: 'Send back to triage' }).click();
        const modal = page.locator('#send-back-modal');
        await expect(modal).toBeVisible();
        await modal.getByLabel('Reason').fill('Overtime is handled by Finance, not HR.');
        await modal.getByRole('button', { name: 'Send back' }).click();

        await expect(page.getByText(`Ticket ${reference} was sent back to triage.`)).toBeVisible();
        await page.goto('/tickets?view=triage');
        await expect(page.getByRole('link', { name: reference })).toBeVisible();

        await page.getByRole('link', { name: reference }).click();
        await expect(page.locator('.timeline-internal', { hasText: 'Sent back to triage: Overtime is handled by Finance' })).toBeVisible();
        await expect(page.locator('#triage-card')).toBeVisible();
        await close();
    });

    test('requester never sees the internal reason', async ({ browser }) => {
        const { page, close } = await asRole(browser, 'requester');
        await page.goto(`/tickets/${reference}`);
        await expect(page.getByText('Overtime is handled by Finance')).toHaveCount(0);
        await expect(page.locator('.badge', { hasText: 'Needs triage' }).first()).toBeVisible();
        await close();
    });

    test('reports show triage metrics', async ({ browser }) => {
        const { page, close } = await asRole(browser, 'admin');
        await page.goto('/reports');
        await expect(page.locator('.info-box', { hasText: 'Avg time to triage' })).toBeVisible();
        await expect(page.locator('.info-box', { hasText: 'Awaiting triage now' }).locator('.info-box-number')).toHaveText(/^[1-9]\d*$/);
        await close();
    });
});

test('admin can grant triage access to an agent', async ({ browser }) => {
    const { page, close } = await asRole(browser, 'admin');
    await page.goto('/admin/users?q=ben.cruz');
    await page.locator('tr', { hasText: 'ben.cruz@example.com' }).getByRole('link').first().click();
    await page.getByLabel(/Triage access/).check();
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.locator('tr', { hasText: 'ben.cruz@example.com' }).locator('.badge', { hasText: 'Triage' })).toBeVisible();
    await close();
});

