# Session Handoff

Ultimo aggiornamento: 2026-10-04

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

## Da fare alla prossima sessione
1. Mettere in `.env`: PAYPAL_CLIENT_ID, PAYPAL_CLIENT_SECRET, PAYPAL_MODE=sandbox (opz. PAYPAL_PRICE_MONTHLY/YEARLY, PAYPAL_CURRENCY).
2. Ricostruire il web: `docker compose -p santo --env-file ~/.env up -d --build --no-deps web-automatico`
3. Provare nel browser (http://localhost:4200, `npx ng serve --host 0.0.0.0 --port 4200` in app-mobile; `npm ci --ignore-scripts`):
   registrazione -> trial; scadenza (UPDATE users SET trial_start_date = NOW() - INTERVAL 8 DAY) -> paywall; pagamento sandbox -> active.
4. Decidere prezzo definitivo.

## Limiti noti
- Token API base64 non firmati (forgiabili): servono JWT firmati per enforcement server vero.
- Pagamento singolo senza rinnovo automatico; manca webhook PayPal; redirect solo web (no deep link nativo).
- Operatori esistenti backfillati in trial dal 2026-10-04 (scadono ~2026-10-11).
- Node 18 (Playwright richiede >=20); node-sass richiede `--ignore-scripts`.
