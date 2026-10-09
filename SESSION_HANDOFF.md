# Session Handoff

Ultimo aggiornamento: 2026-10-09

## Stato consegna
- Branch di lavoro: `work/clean-app-2026-10-04` (allineato a origin all'inizio della sessione).
- Le modifiche di questa sessione sono ancora nel worktree e non sono committate.
- Non fare lavoro su `master` della HOME (`/home/santo`): e' divergente da origin (traccia file di home/cache). Lavorare solo su questo branch, in un worktree pulito:
  `git clone -b work/clean-app-2026-10-04 https://github.com/santocimo/mio-primo-devops.git` oppure `git worktree add /tmp/santo-clean work/clean-app-2026-10-04`

## Cosa include
- Applicazione ripristinata su base pulita (api, app-mobile, migrations, tests, src, scripts, docker-compose).
- Prova gratuita di 7 giorni per palestra, calcolata dal server dalla registrazione
  (`gym_subscriptions.trial_start_date`; `inc/subscription.php::compute_gym_subscription`;
  login/register/verify_token restituiscono `subscription`).
  Guard dell'app senza piu' bypass operatore: a trial scaduto -> `/paywall`.
- Pagamenti ricorrenti B2B a livello palestra: un piano mensile o annuale per palestra,
  referente di fatturazione e cancellazione a fine periodo. Il referente può essere
  separato dagli operatori, coincidere con uno di loro oppure essere l'unico operatore.
  Stripe Checkout e PayPal Subscriptions sono implementati nel codice ma non ancora
  provati in sandbox. Prezzi provvisori: 4,99 EUR/mese e 49,99 EUR/anno.
- Le API usano token bearer firmati HMAC con `APP_TOKEN_SECRET` e rileggono ruolo,
  palestra e abbonamento dal database ad ogni richiesta. Token precedenti richiedono un nuovo login.
- Trial scaduto, token alterato/scaduto o richiesta non autenticata non accedono alle API.
- Rimossi i fallback di login statici e la falsa azione "ripristina acquisti".
- `gym_subscriptions` condivide trial/accesso tra utenti della palestra; il referente
  gestisce checkout e disdetta. La gestione utenti consente ora di aggiungere il ruolo
  GESTORE (referente separato) o nominare un operatore esistente come referente. Solo il
  referente corrente o un admin può trasferire la fatturazione; il referente non può essere
  eliminato prima di aver trasferito la responsabilità. Resta possibile che il referente
  sia anche operatore o sia l'unico operatore.
  Il backend migra i trial e gli abbonamenti individuali esistenti verso una riga per palestra.
- La cancellazione account di un operatore rimuove solo quell'utente; il referente non
  può eliminare la palestra finché il periodo pagato non è terminato e non può cancellare
  il proprio account se ci sono altri utenti, finché non trasferisce la fatturazione.
- Stripe e PayPal hanno checkout ricorrente, endpoint di cancellazione, webhook firmati/
  verificati e ledger rinnovi idempotente. Non eseguiti acquisti sandbox.
  Nuova configurazione: `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`,
  `APP_FRONTEND_URL`, `PLAN_MONTHLY_PRICE`, `PLAN_YEARLY_PRICE`, `PAYMENT_CURRENCY`.
  PayPal: `PAYPAL_PLAN_MONTHLY_ID`, `PAYPAL_PLAN_YEARLY_ID`, `PAYPAL_WEBHOOK_ID`.
  Webhook: `/api/payments/stripe_webhook.php` e `/api/payments/paypal_webhook.php`.
- Il checkout mobile è ancora web-hosted; StoreKit/Google Play Billing e deep link nativi
  non sono implementati. Confermare i requisiti store per il modello B2B prima del rilascio.
- Verifiche superate: test mirati sul referente separato e sull'operatore unico (2 test /
  9 asserzioni), suite PHPUnit completa (12 test / 31 asserzioni), build Angular production,
  lint sintassi PHP sugli endpoint/helper modificati e `git diff --check`.
- Creato backup DB prima della migrazione schema: `~/backups-db/mio_database_20261009_104649.sql`.
- La migrazione non è stata applicata: il container backend attivo non monta questo checkout
  e nel container mancano tutte le credenziali/ID provider di sandbox. Non riavviare quel
  container aspettandosi di caricare queste modifiche; prima preparare una build sandbox.
- I provider non sono stati provati; nessuna transazione live è stata avviata.
  I prezzi restano provvisori. Le modifiche di implementazione sono complete e verificate.

## Da fare alla prossima sessione
1. Con il backup eseguito, applicare la migrazione `gym_subscriptions`; verificare lo stato
   di accesso condiviso, l'identità del referente migrato e i permessi per ciascun caso.
2. Configurare Stripe test mode e PayPal sandbox con piani/webhook, quindi provare checkout,
   rinnovo, disdetta, webhook duplicato e firma errata. Non attivare live.
3. Decidere i prezzi definitivi prima del lancio e provare su dispositivo il redirect
   mobile (attualmente il callback usa route web; manca deep link nativo).
4. Cambiare in ogni ambiente esistente le password amministrative e impostare APP_TOKEN_SECRET
   nello store segreti del deployment; la chiave locale di sviluppo non va riutilizzata in produzione.
5. Eseguire i test browser con Node.js 20+ e credenziali dedicate
   (`E2E_ADMIN_USERNAME`, `E2E_ADMIN_PASSWORD`).

## Limiti noti
- I flussi ricorrenti sono implementati ma non ancora verificati con sandbox. Checkout web
  only; StoreKit/Google Play Billing e deep link nativi non sono implementati.
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
