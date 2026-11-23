import { test, expect } from '@playwright/test';

const BASE = process.env.E2E_BASE_URL ?? 'http://localhost:4200';
const API_BASE = process.env.E2E_API_BASE_URL ?? 'http://localhost:8083';

test('registrazione richiede città, telefono, codice fiscale e conferma password', async ({ page }) => {
  await page.goto(`${BASE}/register`);
  const fill = async (label: string, value: string) => {
    await page.getByRole('textbox', { name: label, exact: true }).fill(value);
  };
  await fill('Nome attività', 'Palestra Test');
  await fill('Indirizzo attività', 'Via Test 10');
  await page.getByText('Palestra', { exact: false }).filter({ hasText: '🏋' }).click();
  await page.getByRole('button', { name: /Continua/ }).click();
  await expect(page.getByText('Inserisci una città valida', { exact: false })).toBeVisible();
  await expect(page.getByText('Inserisci un telefono valido', { exact: false })).toBeVisible();

  await fill('Città', 'Milano');
  await fill('Telefono', '0200000000');
  await page.getByRole('button', { name: /Continua/ }).click();
  await fill('Nome', 'Mario');
  await fill('Cognome', 'Rossi');
  await fill('Email', 'mario@example.test');
  await fill('Username', 'mario.test');
  await fill('Password', 'password123');
  await page.getByRole('button', { name: /Registrati e accedi/ }).click();
  await expect(page.getByText('Codice fiscale non valido', { exact: false })).toBeVisible();
  await expect(page.getByText('Conferma la password', { exact: true })).toBeVisible();

  await fill('Codice fiscale', 'RSSMRA80A01H501U');
  await fill('Conferma password', 'password123');
  let submitted = false;
  await page.route('**/api/register', async route => {
    submitted = true;
    await route.fulfill({ json: { success: false, message: 'Test' } });
  });
  await page.getByRole('button', { name: /Registrati e accedi/ }).click();
  await expect.poll(() => submitted).toBe(true);
});

test('API registrazione rifiuta i nuovi campi obbligatori assenti o vuoti', async ({ request }) => {
  const username = `validation.${Date.now().toString(36)}`;
  const password = 'Test-Password123';
  const valid = {
    gym_name: 'Palestra Test', gym_category: 'gym', gym_address: 'Via Test 10',
    gym_city: 'Milano', gym_phone: '0200000000', name: 'Mario', surname: 'Rossi',
    codice_fiscale: 'RSSMRA80A01H501U', email: `${username}@example.test`,
    username, password,
  };
  for (const field of ['gym_city', 'gym_phone', 'codice_fiscale'] as const) {
    for (const value of ['', '   ']) {
      const response = await request.post(`${API_BASE}/api/register.php`, {
        data: { ...valid, [field]: value },
      });
      if (response.ok()) {
        const body = await response.json();
        if (body.success && body.token) {
          const deletion = await request.post(`${API_BASE}/api/account/delete.php`, {
            headers: { Authorization: `Bearer ${body.token}` },
            data: { password, confirmation: 'ELIMINA' },
          });
          expect((await deletion.json()).activity_deleted).toBe(true);
        }
      }
      expect(response.status(), `${field}=${JSON.stringify(value)}`).toBe(400);
    }
  }
});

test('API registrazione accetta i campi obbligatori completi', async ({ request }) => {
  const username = `validation.${Date.now().toString(36)}`;
  const password = 'Test-Password123';
  const response = await request.post(`${API_BASE}/api/register.php`, {
    data: {
      gym_name: 'Palestra Test', gym_category: 'gym', gym_address: 'Via Test 10',
      gym_city: 'Milano', gym_phone: '0200000000', name: 'Mario', surname: 'Rossi',
      codice_fiscale: 'RSSMRA80A01H501U', email: `${username}@example.test`,
      username, password,
    },
  });
  const body = await response.json();
  try {
    expect(response.status()).toBe(200);
    expect(body.success).toBe(true);
    expect(body.user.role).toBe('gestore');
    expect(body.subscription.status).toBe('trial');
    const headers = { Authorization: `Bearer ${body.token}` };
    const login = await request.post(`${API_BASE}/api/auth/login.php`, { data: { username, password } });
    expect((await login.json()).user.role).toBe('gestore');
    const gyms = await (await request.get(`${API_BASE}/api/gyms.php`, { headers })).json();
    expect(Number(gyms[0].billing_owner_user_id)).toBe(body.user.id);
    for (const endpoint of ['contacts', 'services', 'appointments']) {
      expect((await request.get(`${API_BASE}/api/${endpoint}.php`, { headers })).status()).toBe(200);
    }
  } finally {
    if (body.success && body.token) {
      const deletion = await request.post(`${API_BASE}/api/account/delete.php`, {
        headers: { Authorization: `Bearer ${body.token}` },
        data: { password, confirmation: 'ELIMINA' },
      });
      expect((await deletion.json()).activity_deleted).toBe(true);
    }
  }
});

test('cancellazione attività conserva gli amministratori associati', async ({ request }) => {
  const adminUsername = process.env.E2E_ADMIN_USERNAME;
  const adminPassword = process.env.E2E_ADMIN_PASSWORD;
  test.skip(!adminUsername || !adminPassword, 'Set E2E_ADMIN_USERNAME and E2E_ADMIN_PASSWORD');
  const suffix = Date.now().toString(36);
  const username = `owner.${suffix}`;
  const secondaryAdmin = `admin.${suffix}`;
  const password = 'Test-Password123';
  const login = await request.post(`${API_BASE}/api/auth/login.php`, {
    data: { username: adminUsername, password: adminPassword },
  });
  const admin = await login.json();
  expect(admin.success).toBe(true);
  const adminHeaders = { Authorization: `Bearer ${admin.token}` };
  const registration = await request.post(`${API_BASE}/api/register.php`, { data: {
    gym_name: `Test ${suffix}`, gym_category: 'gym', gym_address: 'Via Test 10',
    gym_city: 'Milano', gym_phone: '0200000000', name: 'Laura', surname: 'Test',
    codice_fiscale: 'TEST000000000001', email: `${username}@example.test`, username, password,
  } });
  const owner = await registration.json();
  expect(owner.success).toBe(true);
  const ownerHeaders = { Authorization: `Bearer ${owner.token}` };
  let secondaryAdminId: number | undefined;
  let activityDeleted = false;
  try {
    const creation = await request.post(`${API_BASE}/api/users.php`, {
      headers: adminHeaders,
      data: { name: 'Admin Test', email: `${secondaryAdmin}@example.test`, username: secondaryAdmin, password, role: 'ADMIN', gym_id: owner.user.gym_id },
    });
    const created = await creation.json();
    expect(created.success).toBe(true);
    secondaryAdminId = created.id;
    const deletion = await request.post(`${API_BASE}/api/account/delete.php`, {
      headers: ownerHeaders, data: { password, confirmation: 'ELIMINA' },
    });
    expect(deletion.status()).toBe(200);
    expect((await deletion.json()).activity_deleted).toBe(true);
    activityDeleted = true;
    const secondaryLogin = await request.post(`${API_BASE}/api/auth/login.php`, {
      data: { username: secondaryAdmin, password },
    });
    const secondary = await secondaryLogin.json();
    expect(secondary.success).toBe(true);
    expect(secondary.user.gym_id).toBeNull();
  } finally {
    if (secondaryAdminId) {
      expect((await request.delete(`${API_BASE}/api/users.php/${secondaryAdminId}`, { headers: adminHeaders })).status()).toBe(200);
    }
    if (!activityDeleted) {
      const deletion = await request.post(`${API_BASE}/api/account/delete.php`, {
        headers: ownerHeaders, data: { password, confirmation: 'ELIMINA' },
      });
      expect((await deletion.json()).activity_deleted).toBe(true);
    }
  }
});

test('il referente crea un operatore dalla gestione della propria attività', async ({ page, request }) => {
  const suffix = Date.now().toString(36);
  const username = `owner.${suffix}`;
  const operatorUsername = `operator.${suffix}`;
  const password = 'Test-Password123';
  const response = await request.post(`${API_BASE}/api/register.php`, { data: {
    gym_name: `Test ${suffix}`, gym_category: 'gym', gym_address: 'Via Test 10',
    gym_city: 'Milano', gym_phone: '0200000000', name: 'Laura', surname: 'Test',
    codice_fiscale: 'TEST000000000001', email: `${username}@example.test`, username, password,
  } });
  const owner = await response.json();
  expect(owner.success).toBe(true);
  const headers = { Authorization: `Bearer ${owner.token}` };
  try {
    await page.addInitScript(state => {
      localStorage.setItem('authState', JSON.stringify(state));
      localStorage.setItem('businessregistry-language', 'it');
    }, {
      isLoggedIn: true, subscriptionStatus: 'trial', trialStartDate: owner.subscription.trial_start_date,
      token: owner.token, user: owner.user, selectedGymId: owner.user.gym_id,
    });
    await page.goto(`${BASE}/contacts`);
    await page.locator('ion-menu-button').first().click();
    await page.locator('.menu-nav-item').filter({ hasText: 'Gestione operatori' }).click();
    await expect(page.locator('app-admin-users ion-title').first()).toHaveText('Gestione operatori');
    await page.locator('app-admin-users ion-header ion-buttons[slot="end"] ion-button').click();
    const form = page.locator('.modal-sheet');
    await expect(form.locator('ion-title')).toHaveText('Nuovo operatore');
    await form.getByRole('textbox', { name: 'Nome', exact: true }).fill('Marco Test');
    await form.getByRole('textbox', { name: 'Email', exact: true }).fill(`${operatorUsername}@example.test`);
    await form.getByRole('textbox', { name: 'Username *', exact: true }).fill(operatorUsername);
    await form.getByLabel('Password', { exact: true }).fill(password);
    await form.getByRole('button', { name: 'Salva' }).click();
    await expect(page.getByText(`@${operatorUsername}`, { exact: false })).toBeVisible();
    const login = await request.post(`${API_BASE}/api/auth/login.php`, { data: { username: operatorUsername, password } });
    const operator = await login.json();
    expect(operator.success).toBe(true);
    expect(operator.user.role).toBe('operatore');
    expect(operator.user.gym_id).toBe(owner.user.gym_id);
  } finally {
    const usersResponse = await request.get(`${API_BASE}/api/users.php`, { headers });
    const users = await usersResponse.json();
    const operator = users.find((user: { username: string }) => user.username === operatorUsername);
    if (operator) {
      const deletion = await request.delete(`${API_BASE}/api/users.php/${operator.id}`, { headers });
      expect((await deletion.json()).success).toBe(true);
    }
    const deletion = await request.post(`${API_BASE}/api/account/delete.php`, {
      headers, data: { password, confirmation: 'ELIMINA' },
    });
    expect((await deletion.json()).activity_deleted).toBe(true);
  }
});

test('aggiornare la sede non confonde operatore e referente', async ({ request }) => {
  const suffix = Date.now().toString(36);
  const ownerUsername = `owner.${suffix}`;
  const operatorUsername = `operator.${suffix}`;
  const password = 'Test-Password123';
  const registration = await request.post(`${API_BASE}/api/register.php`, { data: {
    gym_name: `Test ${suffix}`, gym_category: 'salon', gym_address: 'Via Test 10',
    gym_city: 'Milano', gym_phone: '0200000000', name: 'Laura', surname: 'Test',
    codice_fiscale: 'TEST000000000001', email: `${ownerUsername}@example.test`,
    username: ownerUsername, password,
  } });
  const owner = await registration.json();
  expect(owner.success).toBe(true);
  const ownerHeaders = { Authorization: `Bearer ${owner.token}` };
  let operatorId: number | undefined;
  let operatorHeaders: { Authorization: string } | undefined;
  let ownershipTransferred = false;
  try {
    const creation = await request.post(`${API_BASE}/api/users.php`, {
      headers: ownerHeaders,
      data: { name: 'Marco Test', email: `${operatorUsername}@example.test`, username: operatorUsername, password, role: 'OPERATORE', tax_code: 'TEST000000000002' },
    });
    const created = await creation.json();
    expect(created.success).toBe(true);
    operatorId = created.id;
    const operatorLogin = await request.post(`${API_BASE}/api/auth/login.php`, { data: { username: operatorUsername, password } });
    const operator = await operatorLogin.json();
    expect(operator.success).toBe(true);
    const operatorToken: string = operator.token;
    operatorHeaders = { Authorization: `Bearer ${operatorToken}` };
    expect((await request.get(`${API_BASE}/api/users.php`, { headers: operatorHeaders })).status()).toBe(200);
    expect((await request.post(`${API_BASE}/api/account/delete.php`, {
      headers: ownerHeaders, data: { password, confirmation: 'ELIMINA' },
    })).status()).toBe(409);
    expect((await request.post(`${API_BASE}/api/users.php`, {
      headers: operatorHeaders, data: { username: `unauthorized.${suffix}`, password, role: 'OPERATORE' },
    })).status()).toBe(403);
    expect((await request.put(`${API_BASE}/api/users.php/${owner.user.id}`, {
      headers: operatorHeaders, data: { username: ownerUsername, role: 'GESTORE' },
    })).status()).toBe(403);
    expect((await request.delete(`${API_BASE}/api/users.php/${operatorId}`, {
      headers: operatorHeaders,
    })).status()).toBe(403);
    const details = {
      name: `Test ${suffix}`, category: 'salon', activity_name: 'Centro estetico',
      address: 'Via Test 10', city: 'Milano', phone: '0200000000',
    };
    const forbiddenUpdate = await request.put(`${API_BASE}/api/gyms.php/${owner.user.gym_id}`, {
      headers: { Authorization: `Bearer ${operatorToken}` },
      data: { ...details, manager_name: 'Nome errato', manager_email: 'wrong@example.test', manager_cf: 'TEST000000000002' },
    });
    expect(forbiddenUpdate.status()).toBe(403);
    const update = await request.put(`${API_BASE}/api/gyms.php/${owner.user.gym_id}`, {
      headers: { Authorization: `Bearer ${operatorToken}` }, data: details,
    });
    expect(update.status()).toBe(403);
    const users = await (await request.get(`${API_BASE}/api/users.php`, { headers: ownerHeaders })).json();
    expect(users.find((u: { username: string }) => u.username === ownerUsername).name).toBe('Laura Test');
    expect(users.find((u: { username: string }) => u.username === operatorUsername).name).toBe('Marco Test');
    expect(users.find((u: { username: string }) => u.username === ownerUsername).tax_code).toBe('TEST000000000001');
    expect(users.find((u: { username: string }) => u.username === operatorUsername).tax_code).toBe('TEST000000000002');
    const gyms = await (await request.get(`${API_BASE}/api/gyms.php`, { headers: ownerHeaders })).json();
    expect(gyms[0].manager_username).toBe(ownerUsername);
    expect(gyms[0].manager_cf).toBe('TEST000000000001');

    const ownerUpdate = await request.put(`${API_BASE}/api/gyms.php/${owner.user.gym_id}`, {
      headers: ownerHeaders,
      data: { ...details, manager_name: 'Laura Aggiornata', manager_email: `${ownerUsername}@example.test`, manager_cf: 'TEST000000000003' },
    });
    expect((await ownerUpdate.json()).success).toBe(true);
    const updatedUsers = await (await request.get(`${API_BASE}/api/users.php`, { headers: ownerHeaders })).json();
    expect(updatedUsers.find((u: { username: string }) => u.username === ownerUsername).name).toBe('Laura Aggiornata');
    expect(updatedUsers.find((u: { username: string }) => u.username === ownerUsername).tax_code).toBe('TEST000000000003');
    expect(updatedUsers.find((u: { username: string }) => u.username === operatorUsername).name).toBe('Marco Test');
    expect(updatedUsers.find((u: { username: string }) => u.username === operatorUsername).tax_code).toBe('TEST000000000002');
    const operatorUpdate = await request.put(`${API_BASE}/api/users.php/${operatorId}`, {
      headers: ownerHeaders,
      data: { name: 'Marco Test', email: `${operatorUsername}@example.test`, username: operatorUsername, role: 'OPERATORE' },
    });
    expect((await operatorUpdate.json()).success).toBe(true);
    const afterUpdate = await (await request.get(`${API_BASE}/api/users.php`, { headers: ownerHeaders })).json();
    expect(afterUpdate.find((u: { username: string }) => u.username === operatorUsername).tax_code).toBe('TEST000000000002');
    const accountUpdate = await request.put(`${API_BASE}/api/users.php/${owner.user.id}`, {
      headers: ownerHeaders,
      data: { name: 'Laura Aggiornata', email: `${ownerUsername}@example.test`, username: ownerUsername, role: 'GESTORE', is_billing_owner: true, tax_code: 'TEST000000000004' },
    });
    expect((await accountUpdate.json()).success).toBe(true);
    const afterAccountUpdate = await (await request.get(`${API_BASE}/api/gyms.php`, { headers: ownerHeaders })).json();
    expect(afterAccountUpdate[0].manager_cf).toBe('TEST000000000004');
    const activityOnly = await request.put(`${API_BASE}/api/gyms.php/${owner.user.gym_id}`, {
      headers: ownerHeaders, data: { ...details, city: 'Torino' },
    });
    expect((await activityOnly.json()).success).toBe(true);
    const afterActivityOnly = await (await request.get(`${API_BASE}/api/gyms.php`, { headers: ownerHeaders })).json();
    expect(afterActivityOnly[0].city).toBe('Torino');
    expect(afterActivityOnly[0].manager_cf).toBe('TEST000000000004');
    const transfer = await request.put(`${API_BASE}/api/users.php/${operatorId}`, {
      headers: ownerHeaders,
      data: { name: 'Marco Test', email: `${operatorUsername}@example.test`, username: operatorUsername, role: 'OPERATORE', is_billing_owner: true },
    });
    expect((await transfer.json()).success).toBe(true);
    ownershipTransferred = true;
    expect((await request.put(`${API_BASE}/api/gyms.php/${owner.user.gym_id}`, {
      headers: operatorHeaders, data: details,
    })).status()).toBe(403);
    expect((await request.post(`${API_BASE}/api/users.php`, {
      headers: ownerHeaders, data: { username: `unauthorized.${suffix}`, password, role: 'OPERATORE' },
    })).status()).toBe(403);
    const editFormerOwner = await request.put(`${API_BASE}/api/users.php/${owner.user.id}`, {
      headers: operatorHeaders,
      data: { name: 'Laura Aggiornata', email: `${ownerUsername}@example.test`, username: ownerUsername, role: 'GESTORE', is_billing_owner: false },
    });
    expect((await editFormerOwner.json()).success).toBe(true);
    const afterFormerOwnerEdit = await (await request.get(`${API_BASE}/api/gyms.php`, { headers: operatorHeaders })).json();
    expect(Number(afterFormerOwnerEdit[0].billing_owner_user_id)).toBe(operatorId);
    const restoreOwner = await request.put(`${API_BASE}/api/users.php/${owner.user.id}`, {
      headers: operatorHeaders,
      data: { name: 'Laura Aggiornata', email: `${ownerUsername}@example.test`, username: ownerUsername, role: 'GESTORE', is_billing_owner: true },
    });
    expect((await restoreOwner.json()).success).toBe(true);
    ownershipTransferred = false;
  } finally {
    if (ownershipTransferred && operatorHeaders) {
      const restoreOwner = await request.put(`${API_BASE}/api/users.php/${owner.user.id}`, {
        headers: operatorHeaders,
        data: { name: 'Laura Aggiornata', email: `${ownerUsername}@example.test`, username: ownerUsername, role: 'GESTORE', is_billing_owner: true },
      });
      expect((await restoreOwner.json()).success).toBe(true);
    }
    if (operatorId) {
      const deletion = await request.delete(`${API_BASE}/api/users.php/${operatorId}`, { headers: ownerHeaders });
      expect((await deletion.json()).success).toBe(true);
    }
    const deletion = await request.post(`${API_BASE}/api/account/delete.php`, {
      headers: ownerHeaders, data: { password, confirmation: 'ELIMINA' },
    });
    expect((await deletion.json()).activity_deleted).toBe(true);
  }
});

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
  await type('Telefono', '0200000000');
  await page.getByText('Palestra', { exact: false }).filter({ hasText: '🏋' }).click();
  await page.getByRole('button', { name: /Continua/ }).click();

  await type('Nome', 'Mario');
  await type('Cognome', 'Rossi');
  await type('Codice fiscale', 'RSSMRA80A01H501U');
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
