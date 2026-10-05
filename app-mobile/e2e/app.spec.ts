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
