import { test, expect, Page } from '@playwright/test';

const BASE = process.env.E2E_BASE_URL ?? 'http://localhost:4200';
const API_BASE = process.env.E2E_API_BASE_URL ?? 'http://localhost:8083';
const ADMIN_USERNAME = process.env.E2E_ADMIN_USERNAME;
const ADMIN_PASSWORD = process.env.E2E_ADMIN_PASSWORD;

async function login(page: Page): Promise<void> {
  test.skip(!ADMIN_USERNAME || !ADMIN_PASSWORD, 'Set E2E_ADMIN_USERNAME and E2E_ADMIN_PASSWORD');
  await page.goto(`${BASE}/login`);
  await page.locator('ion-input').nth(0).click();
  await page.keyboard.type(ADMIN_USERNAME!);
  await page.locator('ion-input').nth(1).click();
  await page.keyboard.type(ADMIN_PASSWORD!);
  await page.locator('ion-button[type="submit"]').click();
  await page.waitForURL(/\/(contacts|paywall)/, { timeout: 15000 });
}

test('login con credenziali errate resta nella pagina di accesso', async ({ page }) => {
  await page.goto(`${BASE}/login`);
  await page.locator('ion-input').nth(0).click();
  await page.keyboard.type('invalid-e2e-user');
  await page.locator('ion-input').nth(1).click();
  await page.keyboard.type('invalid-e2e-password');
  await page.locator('ion-button[type="submit"]').click();
  await expect(page).toHaveURL(/\/login/);
});

test('le API protette rifiutano richieste senza token', async ({ request }) => {
  const response = await request.get(`${API_BASE}/api/contacts.php`);
  expect(response.status()).toBe(401);
});

test('contatti mostrano clienti per centri estetici e iscritti per palestre', async ({ page }) => {
  await page.addInitScript(() => {
    localStorage.setItem('authState', JSON.stringify({
      isLoggedIn: true,
      subscriptionStatus: 'active',
      token: 'test-token',
      user: { id: 1, name: 'Admin', username: 'admin', role: 'super', gym_id: null },
      selectedGymId: 16,
    }));
    localStorage.setItem('businessregistry-language', 'it');
  });
  await page.route('**/api/gyms**', route => route.fulfill({ json: [
    { id: 16, name: 'CuraPeople', category: 'salon' },
    { id: 30, name: 'Lion', category: 'gym' },
  ] }));
  await page.route('**/api/contacts**', route => route.fulfill({ json: [] }));

  await page.goto(`${BASE}/contacts`);
  await expect(page.locator('app-contacts > ion-header ion-title').first()).toHaveText('Clienti');
  await expect(page.getByText('Nessun cliente trovato')).toBeVisible();
  await expect(page.getByText('Registro clienti')).toBeVisible();
  await page.locator('app-contacts > ion-header ion-buttons[slot="end"] ion-button').last().click();
  await expect(page.locator('.modal-sheet ion-title')).toHaveText('Nuovo cliente');
  await page.getByText('Chiudi', { exact: true }).click();

  await page.locator('ion-menu-button').first().click();
  await expect(page.locator('.menu-nav-item').filter({ hasText: 'Clienti' })).toBeVisible();
  await page.locator('ion-select').filter({ hasText: 'CuraPeople' }).click();
  await page.getByRole('radio', { name: 'Lion' }).click();
  await page.locator('.menu-nav-item').filter({ hasText: 'Iscritti' }).click();
  await expect(page.locator('app-contacts > ion-header ion-title').first()).toHaveText('Iscritti');
  await expect(page.getByText('Nessun iscritto trovato')).toBeVisible();
  await page.locator('app-contacts > ion-header ion-buttons[slot="end"] ion-button').last().click();
  await expect(page.locator('.modal-sheet ion-title')).toHaveText('Nuovo iscritto');
});

test('scheda attività distingue referente e operatori', async ({ page }) => {
  await page.addInitScript(() => {
    localStorage.setItem('authState', JSON.stringify({
      isLoggedIn: true, subscriptionStatus: 'active', token: 'test-token', selectedGymId: 16,
      user: { id: 10, name: 'Fabrizio Orilio', username: 'fabrizio.orilio', role: 'operatore', gym_id: 16 },
    }));
    localStorage.setItem('businessregistry-language', 'it');
  });
  let updatePayload: Record<string, unknown> | undefined;
  await page.route('**/api/gyms**', async route => {
    if (route.request().method() === 'PUT') {
      updatePayload = route.request().postDataJSON();
      await route.fulfill({ json: { success: true } });
      return;
    }
    await route.fulfill({ json: [{
      id: 16, name: 'CuraPeople', category: 'salon', manager_username: 'fabrizio.orilio',
      manager_name: 'Fabrizio Orilio', manager_email: 'fabrizio@example.test',
      manager_cf: 'TEST000000000016', address: 'Via Test', city: 'Milano', phone: '0200000000',
    }] });
  });
  await page.route('**/api/users**', route => route.fulfill({ json: [
    { id: 25, name: 'Elena Viola', username: 'elena.viola', email: 'elena@example.test', tax_code: 'TEST000000000025', role: 'GESTORE', gym_id: 16, is_billing_owner: 1 },
    { id: 10, name: 'Fabrizio Orilio', username: 'fabrizio.orilio', email: 'fabrizio@example.test', tax_code: 'TEST000000000010', role: 'OPERATORE', gym_id: 16, is_billing_owner: 0 },
    { id: 30, name: 'Altro Operatore', username: 'altro.operatore', role: 'OPERATORE', gym_id: 30, is_billing_owner: 0 },
  ] }));
  await page.goto(`${BASE}/gym`);
  const details = page.locator('.gym-profile-grid').first();
  await expect(details.getByText('Elena Viola')).toBeVisible();
  await expect(details.getByText('elena.viola')).toBeVisible();
  await expect(details.getByText('TEST000000000025')).toBeVisible();
  await expect(details.getByText('TEST000000000016')).toHaveCount(0);
  const operators = page.locator('section').filter({ hasText: 'Operatori della sede' });
  await expect(operators.getByText('Fabrizio Orilio')).toBeVisible();
  await expect(operators.getByText('TEST000000000010', { exact: false })).toBeVisible();
  await expect(operators.getByText('Altro Operatore')).toHaveCount(0);
  await page.getByLabel('Modifica scheda attività').click();
  await expect(page.locator('ion-input').filter({ hasText: 'Nome e cognome gestore' }).locator('input')).toHaveAttribute('readonly', '');
  await page.getByRole('button', { name: 'Salva modifiche' }).click();
  await expect.poll(() => updatePayload).toBeDefined();
  expect(updatePayload).not.toHaveProperty('manager_name');
  expect(updatePayload).not.toHaveProperty('manager_email');
  expect(updatePayload).not.toHaveProperty('manager_cf');
});

test('scheda attività segnala errori di caricamento utenti senza nascondere la sede', async ({ page }) => {
  await page.addInitScript(() => {
    localStorage.setItem('authState', JSON.stringify({
      isLoggedIn: true, subscriptionStatus: 'active', token: 'test-token', selectedGymId: 16,
      user: { id: 10, name: 'Fabrizio', role: 'operatore', gym_id: 16 },
    }));
    localStorage.setItem('businessregistry-language', 'it');
  });
  await page.route('**/api/gyms**', route => route.fulfill({ json: [
    { id: 16, name: 'CuraPeople', category: 'salon' },
  ] }));
  await page.route('**/api/users**', route => route.fulfill({ status: 403, json: { success: false } }));
  await page.goto(`${BASE}/gym`);
  await expect(page.getByRole('heading', { name: 'CuraPeople' })).toBeVisible();
  await expect(page.getByRole('alert')).toContainText('Impossibile caricare il referente e gli operatori');
  await expect(page.getByText('Nessun operatore associato.')).toHaveCount(0);
});

test.describe('Sessione amministratore', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('accesso valido apre iscritti o paywall', async ({ page }) => {
    await expect(page).toHaveURL(/\/(contacts|paywall)/);
  });

  test('naviga tra le sezioni principali dal menu', async ({ page }) => {
    test.skip(page.url().includes('/paywall'), 'Admin account is unexpectedly paywalled');
    const sections = [
      { label: 'Appuntamenti', path: '/appointments' },
      { label: 'Servizi', path: '/services' },
      { label: 'Utenti', path: '/admin/users' },
    ];

    for (const section of sections) {
      await page.locator('ion-menu-button').first().click();
      const item = page.locator('.menu-nav-item').filter({ hasText: section.label }).first();
      await expect(item).toBeVisible();
      await item.click();
      await expect(page).toHaveURL(new RegExp(`${section.path.replace('/', '\\/')}$`));
    }
  });
});
