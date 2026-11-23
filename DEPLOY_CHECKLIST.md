# Checklist di deploy (produzione)

## Blocchi individuati il 23 novembre 2026
- [ ] La build Angular di produzione usa ancora `environment.ts`: aggiungere e verificare la sostituzione con una configurazione HTTPS reale prima della pubblicazione.
- [ ] `.dockerignore` non esclude `.env`: evitare di includere segreti nell'immagine, assicurando prima che tutte le variabili necessarie siano fornite al container. Ricostruire l'immagine e verificare che non contenga file riservati.
- [ ] La protezione Apache in `.htaccess` blocca l'accesso HTTP ai file nascosti; verificare anche la configurazione dell'eventuale proxy e non considerarla sufficiente a rendere pubblico il progetto.
- [ ] Il database locale corretto usa il volume `santo_db_data`: non collegare o trasferire dati locali in produzione senza approvazione. Vedere `RIPRENDI_QUI.md`.
- [ ] `scripts/db_backup.sh` usa come default il vecchio nome del container: per il database locale corrente impostare esplicitamente `DB_CONTAINER=mio-primo-devops-database-santo-1`. Configurare backup isolati e verificarne il ripristino in produzione.
- [ ] Adminer e Portainer non devono essere esposti pubblicamente; la configurazione Compose corrente è per sviluppo.
- [ ] Valutare la rotazione dei segreti presenti nel file `.env` dell'immagine locale: prima del blocco il file era accessibile tramite HTTP sul server di sviluppo.
- [x] Su database nuovo l'admin iniziale non viene associato alla prima attività. La cancellazione dell'attività conserva e scollega eventuali amministratori già associati; verificato su database isolato.
- [x] Aggiornati i test Playwright della sessione amministratore e aggiunto il menu alla pagina profilo.

## 1. Segreti (file `.env` fuori dal repo, mai in git)
- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `DB_PASSWORD` robusta e unica
- [ ] `APP_TOKEN_SECRET`: almeno 32 caratteri casuali (`openssl rand -hex 32`)
- [ ] `ADMIN_INITIAL_PASSWORD` impostata, poi cambiata al primo accesso
- [ ] `APP_FRONTEND_URL` = URL pubblico HTTPS dell'app (usato per i redirect di checkout)
- [ ] Nessuna password di test o `CREDENZIALI_DEV.txt` sul server

## 2. Stripe (da test a live)
- [ ] Attivare l'account Stripe e usare la chiave `sk_live_...` in `STRIPE_SECRET_KEY`
- [ ] Creare nella dashboard live un endpoint webhook verso `https://<dominio>/api/payments/stripe_webhook.php`
      con gli eventi: `checkout.session.completed`, `customer.subscription.created`,
      `customer.subscription.updated`, `customer.subscription.deleted`, `invoice.paid`, `invoice.payment_failed`
- [ ] Copiare il signing secret `whsec_...` del webhook live in `STRIPE_WEBHOOK_SECRET`
      (diverso da quello della CLI di test)
- [ ] Approvare i prezzi finali: `PLAN_MONTHLY_PRICE`, `PLAN_YEARLY_PRICE`, `PAYMENT_CURRENCY`
- [ ] Un pagamento reale di prova, poi rimborso dalla dashboard

## 3. PayPal (rimandato)
- [ ] Credenziali live, piani, `PAYPAL_WEBHOOK_ID` e `PAYPAL_MODE=live`

## 4. Infrastruttura
- [ ] HTTPS con certificato valido (necessario per Stripe e per le app mobili)
- [ ] Porta DB (3306) non esposta pubblicamente
- [ ] Backup DB pianificato (`scripts/db_backup.sh`) e ripristino provato
- [ ] `restart: unless-stopped` attivo; log monitorati
- [ ] Migrazioni applicate (tabelle `gym_subscriptions`, `subscription_payments`)

## 5. Verifica finale
- [ ] `composer test` e `npm run build` (in `app-mobile`) verdi
- [ ] Registrazione, trial, checkout, annullamento e rinnovo provati sull'ambiente reale
- [ ] Pagine legali (termini, privacy, rinnovo automatico) pubblicate
