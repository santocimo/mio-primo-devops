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
- Da fare: Stripe (disdetta, rinnovo, webhook duplicato/firma errata, indagare 400 su invoice.paid) + PayPal sandbox, prezzi definitivi, deep link nativo, cambiare password admin/APP_TOKEN_SECRET in produzione, test Playwright (Node 20+).
- Operatori in trial scadono ~2026-10-11 (gym 1 ora ha abbonamento Stripe di test attivo fino al 2026-11-09).

## Stato 2026-10-09 (Stripe test)
- Backend ricreato dal repo: `docker compose -p santo --env-file /home/santo/.env up -d --build --no-deps web-automatico` (da questa cartella). /tmp/santo-clean non serve piu'.
- Migrazione `gym_subscriptions` applicata (backup ~/backups-db/mio_database_20261009_120750.sql).
- Stripe in test mode: `STRIPE_SECRET_KEY` e `STRIPE_WEBHOOK_SECRET` stanno in /home/santo/.env (mai mostrarli). Stripe CLI in ~/.local/bin/stripe.
- Webhook in locale (va rilanciato dopo riavvio): `STRIPE_API_KEY=$(grep '^STRIPE_SECRET_KEY=' /home/santo/.env | cut -d= -f2-) ~/.local/bin/stripe listen --events checkout.session.completed,customer.subscription.created,customer.subscription.updated,customer.subscription.deleted,invoice.paid,invoice.payment_failed --forward-to http://localhost:8083/api/payments/stripe_webhook.php`. Il `whsec_` e' stabile per questa chiave: se cambia, aggiornare .env e ricreare il backend.
- Checkout di prova riuscito (utente `ope`, piano mensile, carta 4242 4242 4242 4242): abbonamento `active`. Fix fatti: rimosso `payment_method_types` (non piu' supportato) e `current_period_end` letto dagli item (helper `stripe_subscription_period_end_timestamp`).
- Non ancora provati: disdetta, rinnovo, webhook duplicato, firma errata; PayPal (nessuna credenziale); primo webhook `invoice.paid` ha dato 400 (motivo non indagato, probabile arrivo prima di checkout.session.completed).

## Dettagli utili
- Stato 2026-10-07: Git allineato (branch = origin), PHPUnit 8 test OK, build mobile OK, login verificato per tutti gli 8 utenti di test. Non testati: pagamenti Stripe/PayPal, e2e Playwright, dispositivi reali, registrazione completa.
- Utenti nel DB (tutti di test): devtest, admin, ope, operator1, testuser1, testop_20261005, santo.santo, orilio. Password in /home/santo/CREDENZIALI_DEV.txt (fuori dal repo).
- Docker: i container vengono da /tmp/santo-clean/docker-compose.yml (cartella temporanea: se sparisce, usare docker-compose.yml del repo). Altri container: santo-dashboard-db-1.
- Segreti (APP_TOKEN_SECRET, DB_PASSWORD, MAIL_*) stanno in /home/santo/.env: il repo non ha .env. Mai mostrare i valori.
- Login API: POST api/auth/login.php {username,password}; 401 = credenziali errate, "errore di connessione" = backend giù (vedi restart sopra).
- Trucchi: non lanciare test API in parallelo a UPDATE sul DB (falsi 401); dopo reload Ionic puo' dare pagina bianca -> riaprire la pagina; `rg` e `apply_patch` non disponibili, usare grep/edit.
- Backup DB piu' recenti in ~/backups-db (dump 22:56 e 23:04 del 2026-10-07); ripristino: docker exec -i santo-database-santo-1 sh -c 'mariadb -uroot -p"$MYSQL_ROOT_PASSWORD"' < file.sql
- Il vecchio backup_db.sh nella root contiene una password in chiaro: usare scripts/db_backup.sh.
- Password deboli = solo sviluppo locale; in produzione cambiarle (e le password sono nei commit 7916f2e/fd940ed, quindi da considerare note).
