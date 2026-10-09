# Promemoria da incollare a inizio sessione (aggiornato 2026-10-09, sera)

Progetto BusinessRegistry. Leggi prima SESSION_HANDOFF.md nel repo.

**Appena incollato: riavvia `ng serve` (app-mobile, porta 4200) e verifica che il backend su 8083 risponda (se no: docker restart santo-web-automatico-1).**

- Questo file esiste in due copie: nel repo (versionata) e in /home/santo/RIPRENDI_QUI.md. A ogni aggiornamento, modificare quella nel repo, poi `cp RIPRENDI_QUI.md /home/santo/RIPRENDI_QUI.md` e verificare con `cmp`.
- Repo vero: /home/santo/progetti/mio-primo-devops (NON /home/santo, che non e' un repo git).
- Branch di lavoro: work/clean-app-2026-10-04 (su GitHub santocimo/mio-primo-devops). Mai lavorare/pushare su master.
- Prima di iniziare: git status, git branch --show-current, git log --oneline -3; confronta con `git ls-remote origin work/clean-app-2026-10-04`.
- A fine sessione: commit + push del branch (scripts/checkpoint_session.sh) e verifica ls-remote.
- DB: MariaDB in Docker (santo-database-santo-1, db mio_database). Backup: scripts/db_backup.sh -> ~/backups-db (fuori da git). Backup PRIMA di toccare dati/schema. Non mostrare mai segreti/hash.
- Backend: container santo-web-automatico-1 su 8083 (se connection reset: docker restart santo-web-automatico-1). Frontend: app-mobile, `npx ng serve --host 0.0.0.0` (4200).
- Install mobile: npm ci --ignore-scripts. Test: composer test. Build: npm run build.
- Account locali di sviluppo: devtest (ADMIN), admin (SUPER), ope (operatore); password nel DB locale / scripts/create_test_user.php, vedi /home/santo/CREDENZIALI_DEV.txt (fuori dal repo).
- Operatori in trial scadono ~2026-10-11 (gym 1 ora ha abbonamento Stripe di test attivo fino al 2026-11-09).

## Da fare (in ordine di probabile priorita)
1. Prezzi definitivi (ora provvisori 4,99 EUR/mese, 49,99 EUR/anno in .env/compose) e PayPal (rimandati dall'utente).
2. Pubblicazione: API pubblica in HTTPS (dominio), Stripe live (vedi `DEPLOY_CHECKLIST.md`), segreti nuovi, password admin cambiate.
3. Android per lo store: keystore e firma release, icone/splash, versionCode, AAB, eventuali App Links https (`app-mobile/ANDROID.md`). Controllare layout edge-to-edge (Android 15) sul telefono.
4. iOS: serve un Mac con Xcode (`npx cap add ios`, URL scheme in Info.plist). Verificare regole degli store sui pagamenti esterni per abbonamenti B2B.
5. Pagine legali: sono bozze IT/EN (`app-mobile/src/app/pages/legal`); mancano dati del titolare e revisione di un legale.
6. PayPal: il ritorno nell'app via deep link vale solo per Stripe.
7. Test automatici non scritti: sync `invoice.paid` e cancellazione (solo provati a mano; manca sqlite in PHP per mockare il DB).

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
- Dopo riavvio PC: rilanciare `stripe listen` (comando sopra) e `ng serve`; il backend Docker riparte da solo.
- Stato DB di prova: gym 1 abbonamento Stripe test attivo con disdetta a fine periodo (referente `ope`, scade 2026-11-09); gym 12 rimessa in trial.

## Test e qualita
- PHPUnit: `composer test` (17 test). Playwright: vedi sopra (Node 20); `register.spec.ts` registra una palestra, verifica trial e redirect a checkout.stripe.com senza pagare, e si pulisce da solo (elimina l'account via API).
- Backup DB prima di toccare dati: `scripts/db_backup.sh`. Pulizia dati di test in MariaDB: `docker exec santo-database-santo-1 sh -c 'mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" mio_database -e "..."'`.

## Pagine legali
- Bozze IT/EN, route `/legal/:doc` (terms, privacy, subscription), link da registrazione e da `/subscribe`; seguono la lingua scelta nell'app.

## Android / deep link (provato su telefono reale il 2026-10-09: login, checkout Stripe, ritorno nell'app, abbonamento attivo)
- Schema `businessregistry://`. L'app invia `native: true` a `api/payments/stripe.php`, che usa il deep link come success/cancel URL. Listener `appUrlOpen` in `app.component.ts`; la pagina `/subscribe` legge `session_id` in modo reattivo.
- Progetto `app-mobile/android` versionato; targetSdk/compileSdk 35, AGP 8.7.3, Gradle 8.9. Guida: `app-mobile/ANDROID.md`.
- Strumenti in home (senza sudo): JDK 17 `~/.local/jdk17`, SDK `~/Android/sdk` (platform 33/34/35). Non c'e' accesso a KVM: niente emulatore.
- Build APK di prova: `cd app-mobile; DEVICE_API_URL=http://<IP-Windows-Wi-Fi>:8083 ./scripts/build-android-debug.sh`, poi copiare l'APK in `C:\Users\Public\BusinessRegistry-debug.apk`. Debug manifest con `usesCleartextTraffic`.
- Rete telefono (WSL2): il telefono raggiunge il backend via port proxy Windows 8083 -> IP WSL (`172.29.120.160`, cambia al riavvio WSL) + regola firewall. Ricrearlo da PowerShell admin: `netsh interface portproxy add v4tov4 listenport=8083 listenaddress=0.0.0.0 connectport=8083 connectaddress=<IP WSL>`. IP Wi-Fi Windows al momento: 172.20.10.9 (hotspot, puo' cambiare: in tal caso ricompilare l'APK).
- Account di prova per il telefono: crearli con `POST /api/register` e poi eliminarli (Stripe: `DELETE /v1/subscriptions/<id>`; DB: users, gym_subscriptions, subscription_payments, gyms). Quelli di oggi (`prova.tel`, `prova.tel2`) sono gia' stati eliminati.

## Aggiornamento 2026-10-09 sera: iscritti e codice fiscale
- Ripristinati nel popup iscritti ricerca e selezione esplicita del comune con provincia; la lista è resa visibile nel viewport anche su schermi piccoli.
- Il CF viene calcolato/ricalcolato usando nome, cognome, data di nascita, sesso e codice catastale del comune selezionato. CF resta modificabile manualmente.
- Il popup mostra il codice fiscale una sola volta. Sono obbligatori nome, cognome, data di nascita, sesso, comune selezionato e CF valido; indirizzo e telefono restano facoltativi.
- `api/contacts.php` rifiuta POST/PUT con dati obbligatori mancanti, data non valida o checksum CF errato.
- Fix CORS in `cerca_comuni.php`: abilita Authorization nel preflight. Senza questo, il browser non riusciva a fare lookup cross-origin anche se l'endpoint rispondeva via curl.
- Ricostruito solo il backend web locale dopo i fix (nessuna migrazione o modifica dati): `docker compose -p santo --env-file /home/santo/.env up -d --build --no-deps web-automatico`.
- Validazioni: `npm run build` OK; `php -l api/contacts.php` e `php -l cerca_comuni.php` OK; suite Playwright completa seriale 8/8 OK, inclusi selezione comune, CF generato/aggiornato, campo CF singolo e rifiuto API di contatto incompleto. Gli account/record di test sono stati ripuliti.
- Servizi locali verificati: Angular su 4200 HTTP 200; backend su 8083 HTTP 302 (redirect previsto).
- `.phpunit.result.cache` è una cache generata dai test; è stata inclusa nel commit solo su richiesta esplicita di salvare e committare tutto.

## Punti ancora aperti da affrontare
- Restano da fare, nell'ordine sopra: PayPal sandbox (rimandato); decisione prezzi; deploy HTTPS e segreti/password di produzione; release Android/iOS e policy store; dati titolare e revisione legale.
- Restano inoltre da implementare i test automatici per rinnovo/cancellazione webhook Stripe; i flussi sono stati provati manualmente, non in mock automatizzati.
- Non attivare Stripe live né modificare schema/dati senza backup e approvazione dei dettagli commerciali.

## Commit del 2026-10-09 (tutto pushato su origin/work/clean-app-2026-10-04)
Stripe fix e robustezza, test PHPUnit e Playwright, pagine legali IT/EN, deploy checklist, Android + deep link, target SDK 35, fix conferma checkout al rientro.

## Aggiornamento 2026-10-09 (permessi API)
- Verificati i confini tenant degli endpoint contatti, servizi, appuntamenti, utenti e palestre.
- `api/contacts.php`, `api/services.php` e `api/appointments.php` non usano più la palestra 1 come fallback per un utente senza `gym_id`; rispondono 403 per contesto palestra assente. Letture servizi/appuntamenti rifiutano ruoli diversi da admin/operator; anche POST appuntamenti applica il controllo di ruolo.
- `api/gyms.php` riserva la creazione di palestre agli admin; la registrazione pubblica continua tramite il flusso dedicato `api/register.php`.
- Backend ricostruito localmente con `docker compose -p santo --env-file /home/santo/.env up -d --build --no-deps web-automatico`; nessuna modifica a schema o dati. Verificato: frontend 4200 HTTP 200, backend 8083 HTTP 302.
- PHP lint sui quattro endpoint modificati e `composer test` (17 test, 44 assertion) OK; `git diff --check` OK.
- L'aggiornamento è stato committato e pushato su `work/clean-app-2026-10-04`; la copia `/home/santo/RIPRENDI_QUI.md` deve essere sincronizzata con quella versionata.
- Prossimi punti: decidere prezzi e se procedere con PayPal sandbox; non attivare pagamenti live senza approvazione.
