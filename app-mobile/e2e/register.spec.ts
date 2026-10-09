import { test, expect } from '@playwright/test';

const BASE = process.env.E2E_BASE_URL ?? 'http://localhost:4200';
const API_BASE = process.env.E2E_API_BASE_URL ?? 'http://localhost:8083';

test('registrazione palestra: trial attivo e checkout Stripe raggiungibile', async ({ page, request }) => {
  const suffix = Date.now().toString(36);
  const username = `e2e.${suffix}`;
  const password = `E2e-${suffix}-Pwd!`;

  await page.goto(`${BASE}/register`);
  await page.waitForSelector('ion-input');
  const type = async (label: string, value: string) => {
    await page.getByRole('textbox', { name: label, exact: true }).click();
    await page.keyboard.type(value);
  };

  await type('Nome attività', `Palestra E2E ${suffix}`);
  await type('Indirizzo attività', 'Via Roma 10');
  await type('Città', 'Milano');
  await page.getByText('Palestra', { exact: false }).filter({ hasText: '🏋' }).click();
  await page.getByRole('button', { name: /Continua/ }).click();

  await type('Nome', 'Mario');
  await type('Cognome', 'Rossi');
  await type('Email', `${username}@example.com`);
  await type('Username', username);
  await type('Password', password);
  await type('Conferma password', password);
  await page.locator('ion-button.btn-main').first().click();

  await page.waitForURL(/\/contacts/, { timeout: 15000 });

  const login = await request.post(`${API_BASE}/api/auth/login.php`, {
    data: { username, password },
  });
  const body = await login.json();
  const token: string = body.token;
  try {
    expect(body.success).toBe(true);
    expect(body.subscription.status).toBe('trial');
    expect(body.subscription.trial_days_remaining).toBeGreaterThanOrEqual(6);

    await page.goto(`${BASE}/paywall`);
    await expect(page.getByRole('heading', { name: /Sblocca tutte le funzioni/i })).toBeVisible();
    await page.getByRole('button', { name: 'Scegli il piano' }).first().click();
    await page.getByRole('button', { name: 'Paga con carta' }).click();
    await page.waitForURL(/checkout\.stripe\.com/, { timeout: 20000 });
  } finally {
    // Elimina palestra e utente creati dal test (account unico in trial)
    const del = await request.post(`${API_BASE}/api/account/delete.php`, {
      headers: { Authorization: `Bearer ${token}` },
      data: { password, confirmation: 'ELIMINA' },
    });
    expect((await del.json()).activity_deleted).toBe(true);
  }
});
