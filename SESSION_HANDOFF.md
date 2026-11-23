# Session handoff

Aggiornato il 2026-11-23. Per la ripresa operativa vedere `RIPRENDI_QUI.md` e `DEPLOY_CHECKLIST.md`.

## Repository e servizi

- Repo `/home/santo/progetti/mio-primo-devops`, branch `work/clean-app-2026-10-04`; non fare push senza richiesta esplicita.
- Frontend Angular su `127.0.0.1:4200`, backend Docker `mio-primo-devops-web-automatico-1` su 8083, MariaDB `mio-primo-devops-database-santo-1` con database `mio_database` nel volume `santo_db_data`. Il file locale `docker-compose.override.yml` seleziona questo volume: verificare prima di avviare o ricreare il database.
- Il container web non monta i sorgenti: le modifiche PHP sono state copiate nel container attivo e vanno incorporate con un rebuild Compose prima della sua ricreazione.
- Credenziali di test e annotazioni sui CF fittizi sono nel file protetto `/home/santo/CREDENZIALI_DEV.txt`, esterno a Git. Non inserire dati sensibili o segreti negli handoff.
- Prima di cambiare DB o schema creare un backup; quelli esistenti sono in `~/backups-db`.

## Cambiamenti e stato locale

- Account non admin nominativi con password temporanee semplici per test; admin invariato. Credenziali ordinate per attività e tipologia nel file protetto, admin primo e referente prima degli operatori.
- Attività, utenti e ruoli di prova sono documentati nel file locale protetto esterno a Git. Un pagamento annuale Stripe è stato verificato in test; controllare lo stato corrente prima di farvi affidamento.
- La registrazione crea un gestore/referente con funzioni operative. Il referente effettivo può gestire gli operatori della propria attività, anche se il ruolo storico è operatore; l'admin mantiene la gestione globale. Le modifiche agli account sono vietate agli operatori non referenti. Dopo un cambio di ruolo occorre rifare il login.
- Scheda attività: referente reale e altri operatori con CF individuali; il referente non è ripetuto nell'elenco. Admin e gestori possono modificare l'attività; gli operatori, anche se referenti, non possono modificarla né da UI né via API. I dati del referente possono essere cambiati solo da admin o dal gestore referente.
- Su database nuovo l'admin iniziale non viene associato alla prima attività. La cancellazione scollega e conserva gli admin associati, ma resta bloccata quando esistono altri utenti non admin. La colonna `users.gym_id` viene resa nullable sui vecchi schemi ove necessario.
- Login admin porta al profilo, da cui ora è disponibile il menu.
- Aggiunta `users.tax_code`: migrazione `012_add_user_tax_code.sql` già applicata localmente; su database esistenti applicarla prima dell'aggiornamento se la colonna manca. Lo schema iniziale dei nuovi database include la colonna. Il backfill associa il vecchio CF soltanto all'account indicato da `manager_username` nella stessa attività.
- I CF segnaposto `TEST` sono dati di prova non validi per fatturazione reale e vanno sostituiti con dati verificati. Non attribuire il CF storico al referente attuale senza verifica.
- Registrazione: città, telefono e CF richiesti insieme agli altri campi; per Altro è richiesto anche il nome attività personalizzato. Dicitura Clienti per centri estetici e Iscritti per palestre, aggiornata al cambio sede.
- In precedenza: selettore admin con una sola attività, filtri contatti/appuntamenti per sede, creazione contatti admin vincolata alla sede e correzione di nome/cognome in registrazione.

## Verifiche

- Build Angular, lint PHP, PHPUnit 17 test / 44 assertion, 23 test Playwright Chromium e `git diff --check` riusciti. Test di scrittura e migrazione eseguiti su DB isolato; le risorse temporanee sono state rimosse.
- Verificato lo schema iniziale utenti con una tabella temporanea. Playwright ha usato Node 22; Node globale 18 non è sufficiente.
- Test mirati senza creare nuove sessioni di checkout Stripe. Un pagamento annuale di test era stato verificato in precedenza; non equivale a una verifica completa dei pagamenti in produzione.
- Per riprendere: controllare `git status`, ultimo commit e disponibilità dei servizi; proseguire con i test richiesti dall'utente.

## Da decidere prima della produzione

Consultare `DEPLOY_CHECKLIST.md`: URL API produzione, esclusione `.env` dall'immagine senza perdere configurazione, valutazione rotazione segreti potenzialmente esposti, HTTPS, backup con ripristino provato, prezzi e PayPal sandbox, revisione legale, firma e pubblicazione Android/iOS, policy store e test webhook Stripe. Non attivare pagamenti live senza approvazione.
