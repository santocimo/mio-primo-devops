# Session Handoff

Ultimo aggiornamento: 2026-10-07

## Stato consegna
- Branch di lavoro (pulito, basato su origin/master): `work/clean-app-2026-10-04`
- Ultimo commit: vedi `git log` (trial 7 giorni + PayPal)
- Non fare lavoro su `master` della HOME (`/home/santo`): e' divergente da origin (traccia file di home/cache). Lavorare solo su questo branch, in un worktree pulito:
  `git clone -b work/clean-app-2026-10-04 https://github.com/santocimo/mio-primo-devops.git` oppure `git worktree add /tmp/santo-clean work/clean-app-2026-10-04`

## Cosa include
- Applicazione ripristinata su base pulita (api, app-mobile, migrations, tests, src, scripts, docker-compose).
- Prova gratuita 7 giorni per operatori, calcolata dal server dalla registrazione
  (`users.trial_start_date`, `subscription_status`, `subscription_plan`, `subscription_expires_at`;
  `inc/subscription.php::compute_subscription`; login/register/verify_token restituiscono `subscription`).
  Guard dell'app senza piu' bypass operatore: a trial scaduto -> `/paywall`.
- Pagamento PayPal: `api/payments/paypal.php` (create/capture, prezzi solo lato server).
  Prezzi provvisori: 4,99 EUR/mese, 49,99 EUR/anno (da decidere).
- Le API usano token bearer firmati HMAC con `APP_TOKEN_SECRET` e rileggono ruolo,
  palestra e abbonamento dal database ad ogni richiesta. Token precedenti richiedono un nuovo login.
- Trial scaduto, token alterato/scaduto o richiesta non autenticata non accedono alle API.
- Rimossi i fallback di login statici e la falsa azione "ripristina acquisti".
  La pagina piani ora specifica che PayPal esegue pagamenti singoli senza rinnovo automatico.
- In lavorazione: checkout Stripe ospitato e PayPal con piani server-side condivisi,
  prezzi singoli senza rinnovo, ledger idempotente e webhook Stripe firmato.
  Nuova configurazione: `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`,
  `APP_FRONTEND_URL`, `PLAN_MONTHLY_PRICE`, `PLAN_YEARLY_PRICE`, `PAYMENT_CURRENCY`.
  Stripe pubblica il webhook su `/api/payments/stripe_webhook.php`.
- Verifiche già superate dopo le modifiche pagamenti: build Angular production,
  lint sintassi PHP sugli endpoint/helper modificati, PHPUnit (8 test / 17 asserzioni),
  `docker compose config --quiet` e `git diff --check`.
- Nessun provider configurato né pagamento sandbox eseguito: nessuna transazione live
  è stata avviata. I prezzi restano provvisori. Le modifiche non sono ancora committate.

## Da fare alla prossima sessione
1. Configurare Stripe test mode e PayPal sandbox, avviare backend/frontend puliti,
   verificare piani e flussi provider end-to-end; testare anche webhook duplicato/firma errata.
2. Decidere i prezzi definitivi prima del lancio e provare su dispositivo il redirect
   mobile (attualmente il callback usa route web; manca deep link nativo).
3. Cambiare in ogni ambiente esistente le password amministrative e impostare APP_TOKEN_SECRET
   nello store segreti del deployment; la chiave locale di sviluppo non va riutilizzata in produzione.
4. Eseguire i test browser con Node.js 20+ e credenziali dedicate
   (`E2E_ADMIN_USERNAME`, `E2E_ADMIN_PASSWORD`).

## Limiti noti
- Pagamento singolo senza rinnovo automatico; Stripe ha un webhook firmato, PayPal al momento no;
  redirect solo web (no deep link nativo). I provider richiedono credenziali e test sandbox prima del rilascio.
- Prima del deploy pubblico, cambiare le credenziali predefinite di ogni account amministratore gia' esistente.
- I test PHPUnit e la build mobile passano; i test Playwright non sono stati eseguiti qui perche'
  l'ambiente disponibile usa Node 18 (richiesto Node 20+).
- Operatori esistenti backfillati in trial dal 2026-10-04 (scadono ~2026-10-11).
- Per installare le dipendenze legacy di Ionic usare `npm ci --ignore-scripts` (node-sass richiede Python 2).

## Checkpoint 2026-10-07
- Lavoro del 4-5 ottobre recuperato: il repo vero e' `/home/santo/progetti/mio-primo-devops` (la home `/home/santo` NON e' un repo git). Branch `work/clean-app-2026-10-04`, presente su origin. Non lavorare su `master`.
- Verifiche: PHPUnit OK (8 test), build Angular production OK (`npm ci --ignore-scripts && npm run build`).
- Backend Docker: container `santo-web-automatico-1` (porta 8083) + `santo-database-santo-1` (MariaDB, db `mio_database`). Se la 8083 da' connection reset: `docker restart santo-web-automatico-1`.
- Frontend dev: `cd app-mobile && npx ng serve --host 0.0.0.0` (porta 4200, API su http://localhost:8083).
- Account di sviluppo locali: devtest (ADMIN), admin (SUPER), ope (operatore); password deboli solo locali, non riportate qui.
- DB: backup con `scripts/db_backup.sh` (dump in `~/backups-db`, fuori dal repo e non versionato). Ultimo dump: 2026-10-07. Fare un backup prima di ogni modifica allo schema/dati.
- Regola: a fine sessione `git status`, commit e `git push` sul branch di lavoro (o `scripts/checkpoint_session.sh`); verificare con `git ls-remote origin work/clean-app-2026-10-04`.
