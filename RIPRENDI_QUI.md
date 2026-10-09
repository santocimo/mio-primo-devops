# Promemoria da incollare a inizio sessione (aggiornato 2026-10-09)

Progetto BusinessRegistry. Leggi prima SESSION_HANDOFF.md nel repo.

**Appena incollato: riavvia `ng serve` (app-mobile, porta 4200) e verifica che il backend su 8083 risponda (se no: docker restart santo-web-automatico-1).**

- Repo vero: /home/santo/progetti/mio-primo-devops (NON /home/santo, che non e' un repo git).
- Branch di lavoro: work/clean-app-2026-10-04 (su GitHub santocimo/mio-primo-devops). Mai lavorare/pushare su master.
- Prima di iniziare: git status, git branch --show-current, git log --oneline -3; confronta con `git ls-remote origin work/clean-app-2026-10-04`.
- A fine sessione: commit + push del branch (scripts/checkpoint_session.sh) e verifica ls-remote.
- DB: MariaDB in Docker (santo-database-santo-1, db mio_database). Backup: scripts/db_backup.sh -> ~/backups-db (fuori da git). Backup PRIMA di toccare dati/schema. Non mostrare mai segreti/hash.
- Backend: container santo-web-automatico-1 su 8083 (se connection reset: docker restart santo-web-automatico-1). Frontend: app-mobile, `npx ng serve --host 0.0.0.0` (4200).
- Install mobile: npm ci --ignore-scripts. Test: composer test. Build: npm run build.
- Account locali di sviluppo: devtest (ADMIN), admin (SUPER), ope (operatore); password nel DB locale / scripts/create_test_user.php, vedi /home/santo/CREDENZIALI_DEV.txt (fuori dal repo).
- Da fare: PayPal sandbox (rimandato) e prezzi definitivi (rimandati) (Stripe completo), prezzi definitivi, deep link nativo, cambiare password admin/APP_TOKEN_SECRET in produzione, test Playwright (Node 20+).
- Operatori in trial scadono ~2026-10-11 (gym 1 ora ha abbonamento Stripe di test attivo fino al 2026-11-09).

## Stato 2026-10-09 (Stripe test)
- Backend ricreato dal repo: `docker compose -p santo --env-file /home/santo/.env up -d --build --no-deps web-automatico` (da questa cartella). /tmp/santo-clean non serve piu'.
- Migrazione `gym_subscriptions` applicata (backup ~/backups-db/mio_database_20261009_120750.sql).
- Stripe in test mode: `STRIPE_SECRET_KEY` e `STRIPE_WEBHOOK_SECRET` stanno in /home/santo/.env (mai mostrarli). Stripe CLI in ~/.local/bin/stripe.
- Webhook in locale (va rilanciato dopo riavvio): `STRIPE_API_KEY=$(grep '^STRIPE_SECRET_KEY=' /home/santo/.env | cut -d= -f2-) ~/.local/bin/stripe listen --events checkout.session.completed,customer.subscription.created,customer.subscription.updated,customer.subscription.deleted,invoice.paid,invoice.payment_failed --forward-to http://localhost:8083/api/payments/stripe_webhook.php`. Il `whsec_` e' stabile per questa chiave: se cambia, aggiornare .env e ricreare il backend.
- Checkout di prova riuscito (utente `ope`, piano mensile, carta 4242 4242 4242 4242): abbonamento `active`. Fix fatti: rimosso `payment_method_types` (non piu' supportato) e `current_period_end` letto dagli item (helper `stripe_subscription_period_end_timestamp`).
- Provati OK (2026-10-09): firma errata/assente -> 400; webhook firmato inviato due volte -> 200 e una sola riga in `subscription_payments` (idempotente); disdetta via `api/payments/manage.php` solo dal referente (`ope`; devtest -> 403): `cancel_at_period_end=1`, accesso fino a fine periodo, Stripe allineato.
- Ordine eventi Stripe RISOLTO: se `invoice.paid` arriva prima del salvataggio dell'abbonamento, il webhook lo recupera da Stripe (`stripe_webhook_sync_subscription`), lo verifica (metadata, referente, prezzo, valuta, intervallo) e poi registra il pagamento. Provato: 200 e riga nel ledger; sottoscrizione inesistente -> 400.
- Rinnovo provato con Stripe test clock (palestra 12, poi ripulita): +32 giorni -> `invoice.paid` 200, `current_period_end` avanzato di un mese, pagamento di rinnovo nel ledger. 
- PayPal: RIMANDATO su richiesta (2026-10-09). Per la sandbox basta un login su developer.paypal.com (non serve account Business reale): Apps & Credentials > Sandbox > Create App (Merchant), poi Client ID/Secret nel .env (PAYPAL_CLIENT_ID, PAYPAL_CLIENT_SECRET, PAYPAL_MODE=sandbox), creare piani mensile/annuale (PAYPAL_PLAN_MONTHLY_ID/PAYPAL_PLAN_YEARLY_ID) e webhook (PAYPAL_WEBHOOK_ID, serve URL HTTPS raggiungibile).
- Playwright (2026-10-09): Node 20 installato in ~/.local/node20 (nessun sudo). Esecuzione: `cd app-mobile; export PATH=$HOME/.local/node20/bin:$PATH; E2E_ADMIN_USERNAME=devtest E2E_ADMIN_PASSWORD=<da CREDENZIALI_DEV.txt> npx playwright test` -> 6/6 OK. Corretti in modal.spec.ts il selettore del login (cliccava "Registrati") e il titolo atteso ("Modifica iscritto").
- Produzione: nessuna password predefinita nel codice (admin solo da ADMIN_INITIAL_PASSWORD >= 12 caratteri; APP_TOKEN_SECRET >= 32 caratteri). Da fare a deploy: impostare segreti nuovi nello store del deployment e cambiare le password degli account admin esistenti.
- Deep link nativo: NON iniziato. Mancano cartelle android/ios, @capacitor/app e @capacitor/browser; il checkout usa window.location.assign e torna a APP_FRONTEND_URL/subscribe. Serve decidere dominio HTTPS (universal/app link) e provarlo su dispositivo reale.
- Dopo riavvio PC: rilanciare `stripe listen` (comando sopra) e `ng serve`; il backend Docker riparte da solo.
- Stato DB di prova: gym 1 abbonamento Stripe test attivo con disdetta a fine periodo (referente `ope`, scade 2026-11-09); gym 12 rimessa in trial.


## E2E registrazione
- `app-mobile/e2e/register.spec.ts`: registra una palestra, verifica trial e redirect a checkout.stripe.com (senza pagare). Passa. Si pulisce da solo (elimina l account via API a fine test).

- Aggiunti `tests/RecurringSubscriptionsTest.php` (referente, validazioni, conflitto tra palestre, importo). Sync `invoice.paid` e cancellazione restano verificati solo a mano (richiedono Stripe/DB).

- Aggiunta `DEPLOY_CHECKLIST.md` (segreti, passaggio Stripe live, webhook, infrastruttura). Prossimo: pagine legali.

- Pagine legali (bozze IT, da far revisionare a un legale): route `/legal/:doc` (terms, privacy, subscription) in `app-mobile/src/app/pages/legal`, link da registrazione e da `/subscribe`. Build OK.
- Pagine legali ora anche in inglese (segue la lingua scelta nell app).

## Deep link / Android
- Preparato: `@capacitor/app` + `@capacitor/browser`, schema `businessregistry://`, listener in app.component, flag `native` in stripe.php, progetto `app-mobile/android` generato e versionato. Verificato: Stripe accetta il success_url e il backend lo restituisce con native=true. NON provato: build Gradle e giro su dispositivo (manca JDK/SDK). Vedi `app-mobile/ANDROID.md`.
- Android: JDK17+SDK installati in home; `scripts/build-android-debug.sh` produce l APK (build riuscita, copia in C:\Users\Public\BusinessRegistry-debug.apk). Da provare su telefono: serve API raggiungibile dal telefono (WSL2: port proxy Windows o dominio HTTPS).
- Prova su telefono: port proxy Windows 8083->WSL attivo (si perde al riavvio WSL: IP WSL cambia) + regola firewall; IP Wi-Fi Windows 172.20.10.9; APK in C:\Users\Public\BusinessRegistry-debug.apk; debug manifest con usesCleartextTraffic.
