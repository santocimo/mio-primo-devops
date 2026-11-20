# Ripresa progetto BusinessRegistry

Aggiornato il 2026-11-20. Leggere anche `SESSION_HANDOFF.md`.

- Repository: `/home/santo/progetti/mio-primo-devops`; branch `work/clean-app-2026-10-04`. Non lavorare su master.
- All'inizio controllare `git status`, branch e ultimo commit. Non effettuare push senza richiesta.
- Questo promemoria esiste anche in `/home/santo/RIPRENDI_QUI.md`: dopo ogni modifica copiare la versione del repo e verificare con `cmp`.
- Backend locale: container `santo-web-automatico-1` sulla porta 8083; MariaDB: `santo-database-santo-1`, database `mio_database`. Frontend Angular: `app-mobile`, porta 4200. Verificare che siano raggiungibili prima di avviarli nuovamente.
- Il container web non monta i sorgenti: i file PHP aggiornati sono stati copiati nel container in questa sessione, ma andranno persi alla sua ricreazione. Per renderli persistenti nell'immagine, ricostruire il servizio con il normale flusso Docker Compose del progetto.
- Backup prima di modificare dati o schema: `scripts/db_backup.sh`; backup fuori dal repo in `~/backups-db`. La migrazione 012 e la modifica della categoria Pilates Me sono state precedute da backup.
- Credenziali locali di test e annotazioni sui CF fittizi sono nel file protetto `/home/santo/CREDENZIALI_DEV.txt`, fuori dal repo. Non riportare password, hash, CF, email o segreti nel repository o nelle risposte.

## Lavoro recente

- Account di test riordinati per attività: admin invariato; account non admin con nomi utente nominativi e password temporanee semplici. Non usare queste password in produzione.
- CuraPeople: Elena Viola è referente fatturazione, Fabrizio Orilio operatore; condividono la stessa attività e il relativo abbonamento. L'utente ha completato un pagamento annuale Stripe di test; alla verifica risultava attivo fino al 20 novembre 2027. Verificare lo stato corrente prima di basarsi sulla scadenza.
- Lion: Sonia Sonetta e Davide Moretti operatori; Laura Greco referente fatturazione. Pilates Me ha categoria Studio.
- La scheda attività mostra il referente effettivo e gli operatori, con CF individuali. Referente e admin possono modificare i dati del referente dalla scheda; gli altri operatori possono modificare i dati dell'attività senza modificare il referente.
- Registrazione: tutti i campi mostrati sono obbligatori; il nome personalizzato è richiesto solo per la categoria Altro. Per i centri estetici l'app usa Clienti anziché Iscritti; menu e pagina contatti seguono la categoria della sede selezionata.
- La migrazione `migrations/012_add_user_tax_code.sql` è già applicata al DB locale. Copia il CF storico solo se il relativo username corrisponde a un account della stessa attività. I nuovi database includono la colonna nello schema iniziale; applicare la migrazione ai database esistenti che non la possiedono.
- Alcuni CF locali sono segnaposto fittizi `TEST`, non validi per fatturazione reale: sostituirli con dati verificati prima dell'uso reale. Non attribuire automaticamente il vecchio CF dell'attività al referente corrente.
- Aggiornati menu, etichetta referente, ritorno dal paywall e form registrazione. Il file credenziali è ordinato per attività, con admin primo.

## Verifiche e ripresa

- Review e controlli locali: build Angular, lint PHP, PHPUnit (17 test, 44 assertion), 7 test Playwright mirati senza checkout Stripe e `git diff --check` riusciti.
- Un pagamento annuale Stripe è stato verificato in test in precedenza; non è stata effettuata una nuova prova completa dei pagamenti durante la review finale.
- Playwright richiede un Node recente: nella sessione è stato usato Node 22 già disponibile in cache. Il Node globale è 18.
- Alla ripresa verificare i servizi e lo stato Git, poi continuare dai test richiesti dall'utente. Il frontend era attivo su `http://127.0.0.1:4200`.

## Punti aperti

- Prezzi definitivi e PayPal sandbox sono rimandati; non attivare pagamenti live senza approvazione.
- Per la produzione: HTTPS pubblico, segreti e password appropriati, sostituzione dei dati fittizi, revisione legale delle pagine e policy di pagamento degli store.
- Completare firma e pubblicazione Android, verifiche su dispositivo e percorso iOS su Mac/Xcode; dettagli in `app-mobile/ANDROID.md` e `DEPLOY_CHECKLIST.md`.
- Restano da automatizzare i test di rinnovo/cancellazione webhook Stripe, già provati manualmente in passato. Il listener Stripe locale va rilanciato se necessario dopo un riavvio.
