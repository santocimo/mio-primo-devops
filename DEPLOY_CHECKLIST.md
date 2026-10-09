# Checklist di deploy (produzione)

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
