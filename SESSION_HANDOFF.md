# Session Handoff

Ultimo aggiornamento: 2026-10-05

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

## Da fare alla prossima sessione
1. Configurare PayPal sandbox (`PAYPAL_CLIENT_ID`, `PAYPAL_CLIENT_SECRET`, `PAYPAL_MODE=sandbox`)
   e provare acquisto/capture end-to-end.
2. Decidere i prezzi definitivi e, se si vuole checkout con carta, configurare un provider hosted
   (consigliato Stripe Checkout) senza raccogliere dati carta nell'app.
3. Cambiare in ogni ambiente esistente le password amministrative e impostare APP_TOKEN_SECRET
   nello store segreti del deployment; la chiave locale di sviluppo non va riutilizzata in produzione.
4. Eseguire i test browser con Node.js 20+ e credenziali dedicate
   (`E2E_ADMIN_USERNAME`, `E2E_ADMIN_PASSWORD`).

## Limiti noti
- Pagamento singolo senza rinnovo automatico; manca webhook PayPal; redirect solo web (no deep link nativo).
- Prima del deploy pubblico, cambiare le credenziali predefinite di ogni account amministratore gia' esistente.
- I test PHPUnit e la build mobile passano; i test Playwright non sono stati eseguiti qui perche'
  l'ambiente disponibile usa Node 18 (richiesto Node 20+).
- Operatori esistenti backfillati in trial dal 2026-10-04 (scadono ~2026-10-11).
- Per installare le dipendenze legacy di Ionic usare `npm ci --ignore-scripts` (node-sass richiede Python 2).
