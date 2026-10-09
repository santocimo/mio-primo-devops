import { test, expect, Page } from '@playwright/test';

const BASE = 'http://localhost:4200';
const API_BASE = 'http://localhost:8083';
const ADMIN_USERNAME = process.env.E2E_ADMIN_USERNAME;
const ADMIN_PASSWORD = process.env.E2E_ADMIN_PASSWORD;

async function login(page: Page) {
  test.skip(!ADMIN_USERNAME || !ADMIN_PASSWORD, 'Set E2E_ADMIN_USERNAME and E2E_ADMIN_PASSWORD');
  await page.goto(`${BASE}/login`);
  await page.waitForSelector('ion-input', { timeout: 10000 });
  await page.locator('ion-input').first().click();
  await page.keyboard.type(ADMIN_USERNAME!);
  await page.locator('ion-input').nth(1).click();
  await page.keyboard.type(ADMIN_PASSWORD!);
  await page.locator('ion-button[type="submit"]').click();
  await page.waitForURL(/\/(contacts|paywall)/, { timeout: 15000 });
}

async function openContactsPage(page: Page) {
  test.skip(page.url().includes('/paywall'), 'Account does not have app access');
  if (page.url().endsWith('/contacts')) return;
  await page.locator('ion-menu-button').first().click();
  const contactsMenuItem = page.locator('.menu-nav-item:has-text("Iscritti")').first();
  await expect(contactsMenuItem).toBeVisible({ timeout: 5000 });
  await contactsMenuItem.click();
  await expect(page).toHaveURL(/\/contacts/, { timeout: 10000 });
}

test('screenshot popup nuovo contatto', async ({ page }) => {
  await login(page);

  await openContactsPage(page);
  await page.screenshot({ path: 'test-results/contacts-page.png', fullPage: false });

  const addButton = page.locator('ion-header ion-button:has(ion-icon[name="add-outline"])').first();
  await addButton.click();

  const modal = page.locator('.modal-sheet');
  await expect(modal).toBeVisible({ timeout: 5000 });
  await page.screenshot({ path: 'test-results/contacts-modal.png', fullPage: false });
  await page.screenshot({ path: 'test-results/contacts-modal-full.png', fullPage: true });
  await expect(modal.locator('ion-input').first()).toBeVisible();
});

test('modifica contatto mostra i dati facoltativi salvati', async ({ page, request }) => {
  test.skip(!ADMIN_USERNAME || !ADMIN_PASSWORD, 'Set E2E_ADMIN_USERNAME and E2E_ADMIN_PASSWORD');
  const loginResponse = await request.post(`${API_BASE}/api/auth/login.php`, {
    data: { username: ADMIN_USERNAME, password: ADMIN_PASSWORD },
  });
  expect(loginResponse.ok()).toBeTruthy();

  const loginBody = await loginResponse.json();
  const token = loginBody.token;
  const uniqueSuffix = Date.now();
  const cognome = `MODAL${uniqueSuffix}`;

  const createResponse = await request.post(`${API_BASE}/api/contacts.php`, {
    headers: { Authorization: `Bearer ${token}` },
    data: {
      nome: 'AUTO',
      cognome,
      codice_fiscale: 'RSSMRA80A01H501U',
      data_nascita: '1990-01-01',
      luogo_nascita: 'ROMA',
      indirizzo: 'VIA TEST',
      recapito: '1234567890',
      sesso: 'M',
    },
  });
  expect(createResponse.ok()).toBeTruthy();

  const createBody = await createResponse.json();
  const contactId = createBody.id;

  try {
    await login(page);
    await openContactsPage(page);

    const row = page.locator('table.contacts-table tbody tr').filter({ hasText: cognome }).first();
    await expect(row).toBeVisible({ timeout: 10000 });

    await row.locator('ion-button').first().evaluate((element: HTMLElement) => element.click());

    const modal = page.locator('.modal-sheet');
    await expect(modal).toBeVisible({ timeout: 5000 });
    await expect(modal.locator('ion-title')).toContainText('Modifica iscritto');
    await expect(modal.locator('ion-input').nth(3)).toHaveJSProperty('value', 'ROMA');
  } finally {
    await request.delete(`${API_BASE}/api/contacts.php/${contactId}`, {
      headers: { Authorization: `Bearer ${token}` },
    }).catch(() => undefined);
  }
});

test('calcola e aggiorna il codice fiscale usando il comune selezionato', async ({ page, request }) => {
  test.skip(!ADMIN_USERNAME || !ADMIN_PASSWORD, 'Set E2E_ADMIN_USERNAME and E2E_ADMIN_PASSWORD');
  const loginResponse = await request.post(`${API_BASE}/api/auth/login.php`, {
    data: { username: ADMIN_USERNAME, password: ADMIN_PASSWORD },
  });
  expect(loginResponse.ok()).toBeTruthy();
  const token = (await loginResponse.json()).token;
  const incompleteContact = await request.post(`${API_BASE}/api/contacts.php`, {
    headers: { Authorization: `Bearer ${token}` },
    data: { nome: 'TEST', cognome: 'INCOMPLETO' },
  });
  expect(incompleteContact.status()).toBe(400);

  const uniqueSuffix = Date.now();
  const cognome = `ROSSI${uniqueSuffix}`;

  try {
    await login(page);
    await openContactsPage(page);

    const addButton = page.locator('ion-header ion-button:has(ion-icon[name="add-outline"])').first();
    await addButton.click();
    const modal = page.locator('.modal-sheet');
    await expect(modal).toBeVisible();

    const inputs = modal.locator('ion-input input');
    await expect(modal.getByLabel('Codice fiscale *')).toBeVisible();
    await expect(modal.getByLabel('Codice fiscale *')).toHaveCount(1);
    await expect(modal.getByLabel('Comune di nascita *')).toBeVisible();
    await expect(modal.locator('ion-button').last()).toHaveAttribute('aria-disabled', 'true');
    await inputs.nth(0).fill('Mario');
    await inputs.nth(1).fill(cognome);
    await inputs.nth(2).fill('1980-01-01');

    await modal.locator('ion-select').click();
    await page.getByRole('radio', { name: 'Maschio' }).click();

    await inputs.nth(3).fill('Roma');
    const romaOption = modal.locator('.suggestion-item').filter({ hasText: 'ROMA (RM)' });
    await expect(romaOption).toBeVisible();
    await expect(romaOption).toBeInViewport();
    await romaOption.click();
    await expect(inputs.last()).toHaveValue('RSSMRA80A01H501U');
    await modal.locator('ion-button').last().click();

    const row = page.locator('table.contacts-table tbody tr').filter({ hasText: cognome }).first();
    await expect(row).toBeVisible({ timeout: 10000 });
    await row.locator('ion-button').first().evaluate((element: HTMLElement) => element.click());
    await expect(modal).toBeVisible();

    await modal.locator('ion-select').click();
    await page.getByRole('radio', { name: 'Femmina' }).click();
    await expect(inputs.last()).toHaveValue(/^RSSMRA80A41H501[A-Z]$/);
    await modal.locator('ion-button').last().click();
  } finally {
    const response = await request.get(`${API_BASE}/api/contacts.php`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    if (response.ok()) {
      const contacts = await response.json();
      const created = Array.isArray(contacts)
        ? contacts.find((contact: { cognome?: string }) => contact.cognome === cognome)
        : undefined;
      if (created?.id) {
        await request.delete(`${API_BASE}/api/contacts.php/${created.id}`, {
          headers: { Authorization: `Bearer ${token}` },
        });
      }
    }
  }
});
