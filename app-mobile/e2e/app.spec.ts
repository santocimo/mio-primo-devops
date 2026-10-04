import { test, expect, Page } from '@playwright/test';

const BASE = 'http://localhost:4200';

async function login(page: Page) {
  await page.goto(`${BASE}/login`);
  await page.waitForSelector('ion-input[name="username"], input[placeholder*="tente"], input[type="text"]', { timeout: 10000 });
  // Fill username
  const usernameInput = page.locator('ion-input').first();
  await usernameInput.click();
  await page.keyboard.type('admin');
  // Fill password
  const passwordInput = page.locator('ion-input').nth(1);
  await passwordInput.click();
  await page.keyboard.type('admin123');
  // Submit
  await page.locator('ion-button[type="submit"], ion-button').first().click();
  // Wait for redirect away from login
  await page.waitForURL(/\/(dashboard|paywall)/, { timeout: 10000 });
}

test.describe('Login', () => {
  test('login con credenziali valide va su dashboard', async ({ page }) => {
    await login(page);
    await expect(page).toHaveURL(/\/dashboard/);
  });

  test('login con credenziali errate mostra errore', async ({ page }) => {
    await page.goto(`${BASE}/login`);
    await page.waitForSelector('ion-input', { timeout: 10000 });
    await page.locator('ion-input').first().click();
    await page.keyboard.type('admin');
    await page.locator('ion-input').nth(1).click();
    await page.keyboard.type('WRONGPASS');
    await page.locator('ion-button[type="submit"], ion-button').first().click();
    await page.waitForTimeout(2000);
    // Should stay on login
    await expect(page).toHaveURL(/\/login/);
  });
});

test.describe('Navigazione dal menu', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  const pages = [
    { label: 'Dashboard', url: /\/dashboard/, pageTitle: /registro digitale/i },
    { label: 'Contatti', url: /\/contacts/, pageTitle: /contatti/i },
    { label: 'Utenti', url: /\/admin\/users/, pageTitle: /utenti/i },
    { label: 'Sedi', url: /\/admin\/gyms/, pageTitle: /sedi/i },
    { label: 'Servizi', url: /\/admin\/services/, pageTitle: /servizi/i },
    { label: 'Appuntamenti', url: /\/admin\/appointments/, pageTitle: /appuntamenti/i },
    { label: 'Impostazioni', url: /\/admin\/settings/, pageTitle: /impostazioni/i },
  ];

  for (const p of pages) {
    test(`naviga su "${p.label}" dal menu e renderizza la pagina`, async ({ page }) => {
      const menuBtn = page.locator('ion-menu-button').first();
      await menuBtn.click();

      const menuItem = page.locator(`.menu-nav-item:has-text("${p.label}")`).first();
      await expect(menuItem).toBeVisible({ timeout: 5000 });
      await menuItem.click();

      await expect(page).toHaveURL(p.url, { timeout: 10000 });
      await expect(page.locator('ion-header ion-title').filter({ hasText: p.pageTitle }).first()).toBeVisible({ timeout: 5000 });
    });
  }
});

test.describe('API endpoints', () => {
  let token: string;

  test.beforeAll(async ({ request }) => {
    const resp = await request.post('http://localhost:8081/api/auth/login', {
      data: { username: 'admin', password: 'admin123' },
    });
    const body = await resp.json();
    token = body.token;
  });

  test('GET /api/gyms ritorna lista', async ({ request }) => {
    const resp = await request.get('http://localhost:8081/api/gyms', {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(Array.isArray(body)).toBe(true);
  });

  test('GET /api/contacts ritorna lista', async ({ request }) => {
    const resp = await request.get('http://localhost:8081/api/contacts', {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(resp.status()).toBe(200);
  });

  test('GET /api/services ritorna lista', async ({ request }) => {
    const resp = await request.get('http://localhost:8081/api/services', {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(resp.status()).toBe(200);
  });

  test('GET /api/users ritorna lista', async ({ request }) => {
    const resp = await request.get('http://localhost:8081/api/users', {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(resp.status()).toBe(200);
  });

  test('GET /api/appointments ritorna lista', async ({ request }) => {
    const resp = await request.get('http://localhost:8081/api/appointments', {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(resp.status()).toBe(200);
  });
});
