# Checklist di deploy (produzione)

## Stato tecnico aggiornato al 26 novembre 2026

Preparata una configurazione di produzione separata, non avviata e non pubblicata. Non contiene dominio o credenziali reali. Per il rilascio restano necessari DNS e segreti creati sul provider scelto.

- [x] La build Angular di produzione sostituisce `environment.ts` con `environment.prod.ts` e usa API same-origin, senza hostname dimostrativi.
- [x] Il contesto Docker PHP è in allowlist: non copia la home, `.env`, file personali, test o documentazione. Composer crea un autoloader di produzione senza dipendenze `dev`.
- [x] Apache disabilita gli indici e nega l'accesso HTTP alle directory applicative interne e ai file di documentazione/configurazione.
- [x] La configurazione PHP legge i segreti da file montati in Compose e fallisce esplicitamente se un segreto configurato non è leggibile.
- [x] `compose.production.yml` separa frontend, API e MariaDB; solo Caddy pubblica le porte 80/443, il database non ha porte host e non sono inclusi Adminer o Portainer.
- [x] Caddy è predisposto per HTTPS automatico quando il dominio punta al server e le porte pubbliche sono raggiungibili.
- [x] `backup_db.sh` non contiene password, salva fuori dal repository con permessi restrittivi e supporta il container locale corrente.
- [x] Le immagini Docker di backend e frontend si compilano; configurazioni Apache/Nginx e smoke test HTTP sono stati verificati senza avviare il database di produzione.
- [x] Aggiornati Angular, Ionic e Capacitor; build frontend di produzione riuscita e `npm audit` non segnala vulnerabilità.
- [ ] La build APK Android non è stata eseguita perché l'ambiente non dispone di Android SDK. Migrazione Capacitor e `cap sync` completati; Gradle wrapper 8.13 verificato con JDK 21.
- [ ] Creare `.env.production` e i file dei segreti fuori dal repository con valori nuovi; non riutilizzare credenziali di sviluppo potenzialmente esposte.
- [ ] Scegliere dominio/provider, configurare DNS/firewall e verificare l'emissione HTTPS reale prima di accettare dati.
- [ ] Avviare il backup pianificato su hosting e dimostrare un ripristino su database isolato.
- [ ] Verificare che il rilascio includa gli ultimi fix RBAC; eseguire E2E e smoke test su un database di prova, non sul database locale.
- [ ] Non trasferire dati locali in produzione. Il database Compose di produzione usa un volume nuovo e separato.

I dettagli del server locale, compreso il volume corretto `santo_db_data`, restano in `RIPRENDI_QUI.md`. La configurazione Compose locale continua a essere solo per sviluppo.
- [x] Su database nuovo l'admin iniziale non viene associato alla prima attività. La cancellazione dell'attività conserva e scollega eventuali amministratori già associati; verificato su database isolato.
- [x] Aggiornati i test Playwright della sessione amministratore e aggiunto il menu alla pagina profilo.

## 1. Segreti

Per produzione, copiare `.env.production.example` in `.env.production` fuori dal repository e passarlo a Compose con `--env-file`. Le password e le chiavi vanno in file separati fuori dal repository; il file `.env.production` contiene soltanto dominio, nomi DB e percorsi dei file segreti.

- [ ] `DB_ROOT_PASSWORD_FILE` e `DB_APP_PASSWORD_FILE` puntano a password diverse, robuste e uniche
- [ ] `APP_TOKEN_SECRET_FILE` punta a un segreto casuale di almeno 32 caratteri
- [ ] `ADMIN_INITIAL_PASSWORD_FILE` punta a una password iniziale unica, da cambiare al primo accesso
- [ ] `APP_DOMAIN` è il dominio HTTPS pubblico; Compose imposta `APP_URL` e `APP_FRONTEND_URL` da questo valore
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
- [ ] Backup DB pianificato (`scripts/db_backup.sh`) e ripristino provato su database isolato
- [ ] `restart: unless-stopped` attivo; log monitorati
- [ ] Migrazioni applicate (tabelle `gym_subscriptions`, `subscription_payments`)

### Compose di produzione

`compose.production.yml` include Caddy, frontend Nginx, backend PHP e MariaDB. Prima dell'avvio:

1. Configurare `APP_DOMAIN` e i quattro percorsi dei file segreti in `.env.production`.
2. Creare i file dei segreti fuori dal repository e limitarne l'accesso al solo account amministrativo.
3. Puntare il DNS del dominio all'host e consentire traffico in ingresso sulle porte 80 e 443.
4. Verificare la configurazione senza avviare servizi:
   `docker compose --env-file .env.production -f compose.production.yml config --quiet`
5. Avviare solo dopo le verifiche di sicurezza e il backup:
   `docker compose --env-file .env.production -f compose.production.yml up -d --build`

Non eseguire `down -v`: il volume `production_db_data` contiene i dati di produzione.

## 5. Verifica finale
- [x] `composer test`, validazione `composer.lock`, `npm audit` e build Angular di produzione verdi
- [ ] Registrazione, trial, checkout, annullamento e rinnovo provati sull'ambiente reale
- [ ] Pagine legali (termini, privacy, rinnovo automatico) pubblicate
