import { test, expect, Page } from '@playwright/test';

const BASE = 'http://localhost:4200';
const API_BASE = 'http://localhost:8081';

async function login(page: Page) {
  await page.goto(`${BASE}/login`);
  await page.waitForSelector('ion-input', { timeout: 10000 });
  await page.locator('ion-input').first().click();
  await page.keyboard.type('admin');
  await page.locator('ion-input').nth(1).click();
  await page.keyboard.type('admin123');
  await page.locator('ion-button[type="submit"], ion-button').first().click();
  await page.waitForURL(/\/(dashboard|paywall)/, { timeout: 10000 });
}

async function openContactsPage(page: Page) {
  await page.locator('ion-menu-button').first().click();
  const contactsMenuItem = page.locator('.menu-nav-item:has-text("Contatti")').first();
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

test('modifica contatto riapre suggerimenti comuni', async ({ page, request }) => {
  const loginResponse = await request.post(`${API_BASE}/api/auth/login`, {
    data: { username: 'admin', password: 'admin123' },
  });
  expect(loginResponse.ok()).toBeTruthy();

  const loginBody = await loginResponse.json();
  const token = loginBody.token;
  const uniqueSuffix = Date.now();
  const cognome = `MODAL${uniqueSuffix}`;

  const createResponse = await request.post(`${API_BASE}/api/contacts`, {
    headers: { Authorization: `Bearer ${token}` },
    data: {
      nome: 'AUTO',
      cognome,
      codice_fiscale: '',
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

  await page.route('**/cerca_comuni.php?term=*', async route => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify([
        { label: 'ROMA (RM)', value: 'ROMA', codice: 'H501' },
      ]),
    });
  });

  try {
    await login(page);
    await openContactsPage(page);

    const row = page.locator('table.contacts-table tbody tr').filter({ hasText: cognome }).first();
    await expect(row).toBeVisible({ timeout: 10000 });

    await row.locator('ion-button').first().evaluate((element: HTMLElement) => element.click());

    const modal = page.locator('.modal-sheet');
    await expect(modal).toBeVisible({ timeout: 5000 });
    await expect(modal.locator('ion-title')).toContainText('Modifica contatto');
    await expect(page.locator('.suggestion-item').first()).toHaveText('ROMA (RM)', { timeout: 5000 });
  } finally {
    await request.delete(`${API_BASE}/api/contacts/${contactId}`, {
      headers: { Authorization: `Bearer ${token}` },
    }).catch(() => undefined);
  }
});
