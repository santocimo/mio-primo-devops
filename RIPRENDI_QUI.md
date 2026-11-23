# Ripresa progetto BusinessRegistry

Aggiornato il 2026-11-23. Leggere anche `RIPRENDI_QUI_NEXT.txt` nella home e `SESSION_HANDOFF.md`.

## Database locale corretto: attenzione

- Il database di lavoro `mio_database` usa il volume Docker **`santo_db_data`**, non `mio-primo-devops_db_data`. Quest'ultimo era vuoto il 23 novembre: non inizializzarlo, popolarlo o usare backup per sostituire i dati senza prima verificare e concordare l'operazione.
- Avviare Compose dal repository: il file locale `docker-compose.override.yml` seleziona il volume esterno `santo_db_data`. Il file era non tracciato il 23 novembre: non eliminarlo o ignorarlo. Se manca, fermarsi e verificare la configurazione prima di avviare o ricreare il database.
- Dopo aver verificato il volume, la configurazione locale dell'override è:
  ```yaml
  volumes:
    db_data:
      external: true
      name: santo_db_data
  ```
- Controllare il volume effettivamente montato con `docker inspect mio-primo-devops-database-santo-1 --format '{{range .Mounts}}{{.Name}} -> {{.Destination}}{{println}}{{end}}'`. Non usare `docker compose down -v` o eliminare volumi.
- Il 23 novembre, dopo il ripristino del collegamento, erano presenti 11 utenti e 6 attività; il login admin e l'API delle attività sono stati verificati. I conteggi possono cambiare: se i dati sembrano assenti o diversi, fermarsi e indagare, senza crearne di nuovi.
- Backup del volume prima del ricollegamento: `santo-db-before-reconnect-e2beb0fa`. Lasciarlo intatto; eseguire un nuovo backup prima di modificare dati o schema.
- Un servizio che risponde o un database `healthy` non dimostra che l'app funzioni: verificare anche login e dati tramite API, senza esporre credenziali o token.

- Repository: `/home/santo/progetti/mio-primo-devops`; branch `work/clean-app-2026-10-04`. Non lavorare su master.
- All'inizio controllare `git status`, branch e ultimo commit. Non effettuare push senza richiesta.
- Il promemoria locale `/home/santo/RIPRENDI_QUI.md` conserva anche dettagli privati storici: tenerlo coerente con questo file senza copiarne i dati personali nel repository.
- Backend locale: container `mio-primo-devops-web-automatico-1` sulla porta 8083; MariaDB: `mio-primo-devops-database-santo-1`, database `mio_database`. Frontend Angular: `app-mobile`, porta 4200. Verificare che siano raggiungibili prima di avviarli nuovamente.
- Il container web non monta i sorgenti: `api/account/delete.php`, `api/gyms.php`, `src/Database/DatabaseManager.php` e `.htaccess` aggiornati sono stati copiati nel container attivo, ma andranno persi alla sua ricreazione. Ricostruire l'immagine dopo aver risolto l'inclusione di `.env` e verificato la configurazione; non ricreare il database.
- Backup prima di modificare dati o schema: `DB_CONTAINER=mio-primo-devops-database-santo-1 scripts/db_backup.sh`; backup riservati fuori dal repo in `~/backups-db`. La migrazione 012 e le precedenti modifiche a categorie e ruoli sono state precedute da backup.
- Adminer su 8082 usa `santo-dashboard-db-1`, collegato manualmente alla rete `mio-primo-devops_default`: verificare il collegamento se viene ricreato. Portainer è su 9443; nessuno dei due va esposto pubblicamente.
- Credenziali locali di test e annotazioni sui CF fittizi sono nel file protetto `/home/santo/CREDENZIALI_DEV.txt`, fuori dal repo. Non riportare password, hash, CF, email o segreti nel repository o nelle risposte.

## Lavoro recente

- Account di test riordinati per attività: admin invariato; account non admin con nomi utente nominativi e password temporanee semplici. Non usare queste password in produzione.
- Attività, utenti, ruoli e credenziali di prova sono documentati nel file locale protetto esterno al repository. Un pagamento annuale Stripe è stato completato in modalità test; verificare lo stato corrente senza presumere che sia ancora attivo.
- Nuove registrazioni: il primo account è gestore e referente fatturazione, con tutte le funzioni operative. Il referente effettivo, anche se il suo ruolo storico è operatore, vede Gestione operatori e può gestire gli account della propria attività; gli altri operatori non possono modificarli. L'admin continua a gestire tutti gli utenti.
- La scheda attività mostra il referente effettivo e gli altri operatori, senza ripetere il referente, con CF individuali. Admin e gestori possono modificare la scheda; il gestore può modificare i dati del referente solo se è il referente. Gli operatori, anche se referenti, non vedono il pulsante e ricevono 403 se provano a modificare la scheda via API.
- Su database nuovo l'admin iniziale non è associato alla prima attività. Quando un titolare cancella l'attività, gli amministratori associati sono conservati e scollegati; altri utenti non admin continuano a impedire la cancellazione. Lo schema esistente rende `users.gym_id` nullable se necessario: verificare backup prima di applicare a database diversi da quello locale.
- Il login admin apre il profilo, che ora consente anche l'accesso al menu. I test admin sono stati aggiornati.
- Registrazione: tutti i campi mostrati sono obbligatori; il nome personalizzato è richiesto solo per la categoria Altro. Per i centri estetici l'app usa Clienti anziché Iscritti; menu e pagina contatti seguono la categoria della sede selezionata.
- La migrazione `migrations/012_add_user_tax_code.sql` è già applicata al DB locale. Copia il CF storico solo se il relativo username corrisponde a un account della stessa attività. I nuovi database includono la colonna nello schema iniziale; applicare la migrazione ai database esistenti che non la possiedono.
- Alcuni CF locali sono segnaposto fittizi `TEST`, non validi per fatturazione reale: sostituirli con dati verificati prima dell'uso reale. Non attribuire automaticamente il vecchio CF dell'attività al referente corrente.
- Aggiornati menu, etichetta referente, ritorno dal paywall e form registrazione. Il file credenziali è ordinato per attività e tipologia, con admin primo e referente prima degli operatori. Il cambio di ruolo invalida i vecchi token: occorre rifare il login.

## Verifiche e ripresa

- Ultima verifica del 23 novembre: build Angular riuscita, lint PHP e PHPUnit (17 test, 44 assertion) riusciti; Playwright Chromium 23 test riusciti, oltre alla prova della prima registrazione su DB nuovo e migrazione dello schema su DB isolato. Alcuni test UI usano API simulate. `git diff --check` riuscito.
- I test di registrazione e contatti scrivono e cancellano dati: non eseguirli contro `santo_db_data`. Per test di scrittura usare un DB temporaneo, rete Docker interna, backend con credenziali fittizie senza `.env` e frontend dedicato collegato solo al backend di prova. Le risorse temporanee della sessione sono state rimosse.
- Un pagamento annuale Stripe è stato verificato in test in precedenza; non è stata effettuata una nuova prova completa dei pagamenti durante la review finale.
- Playwright richiede un Node recente: nella sessione è stato usato Node 22 già disponibile in cache. Il Node globale è 18.
- Alla ripresa verificare i servizi e lo stato Git, poi continuare dai test richiesti dall'utente. Il frontend era attivo su `http://127.0.0.1:4200`.

## Punti aperti

- Prezzi definitivi e PayPal sandbox sono rimandati; non attivare pagamenti live senza approvazione.
- Prima della pubblicazione risolvere i blocchi di `DEPLOY_CHECKLIST.md`: URL API nella build di produzione ancora locale, `.env` incluso nell'immagine, possibili segreti esposti prima del blocco Apache e necessità di valutare la rotazione, HTTPS pubblico, backup con ripristino provato e protezione degli strumenti amministrativi. Il blocco HTTP dei file nascosti risponde 403, ma non basta per il deploy.
- Completare firma e pubblicazione Android, verifiche su dispositivo e percorso iOS su Mac/Xcode; dettagli in `app-mobile/ANDROID.md` e `DEPLOY_CHECKLIST.md`.
- Restano da automatizzare i test di rinnovo/cancellazione webhook Stripe, già provati manualmente in passato. Il listener Stripe locale va rilanciato se necessario dopo un riavvio.
