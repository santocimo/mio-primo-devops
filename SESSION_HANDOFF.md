# Session handoff

Aggiornato il 2026-11-20. Per la ripresa operativa vedere anche `RIPRENDI_QUI.md`.

## Repository e servizi

- Repo `/home/santo/progetti/mio-primo-devops`, branch `work/clean-app-2026-10-04`; non fare push senza richiesta esplicita.
- Frontend Angular su `127.0.0.1:4200`, backend Docker `santo-web-automatico-1` su 8083, MariaDB `santo-database-santo-1` con database `mio_database`.
- Il container web non monta i sorgenti: le modifiche PHP sono state copiate nel container attivo e vanno incorporate con un rebuild Compose prima della sua ricreazione.
- Credenziali di test e annotazioni sui CF fittizi sono nel file protetto `/home/santo/CREDENZIALI_DEV.txt`, esterno a Git. Non inserire dati sensibili o segreti negli handoff.
- Prima di cambiare DB o schema creare un backup; quelli esistenti sono in `~/backups-db`.

## Cambiamenti e stato locale

- Account non admin nominativi con password temporanee semplici per test; admin invariato. Credenziali ordinate per attività e tipologia nel file protetto, admin primo e referente prima degli operatori.
- CuraPeople: Elena Viola referente, Fabrizio Orilio e Anna Mosca operatori; abbonamento annuale Stripe di test attivo alla verifica fino al 20 novembre 2027. Lion: Sonia Sonetta e Davide Moretti operatori, Laura Greco referente. Pilates Me e PilatesMania: rispettivamente Santo Vasta e Mimmo Luna gestori unici e referenti con funzioni operative; alla verifica del 20 novembre gli abbonamenti erano scaduti.
- Barber100 e Fit: Luca Genna e Fabio Rossi sono gestori unici e referenti con funzioni operative. Ruoli aggiornati dopo backup; login e accesso alle API operative verificati con trial attivo il 20 novembre. I vecchi token non sono più validi.
- La registrazione crea un gestore/referente con funzioni operative. Il referente effettivo può gestire gli operatori della propria attività, anche se il ruolo storico è operatore; l'admin mantiene la gestione globale. Le modifiche agli account sono vietate agli operatori non referenti. Dopo un cambio di ruolo occorre rifare il login.
- Scheda attività: referente reale e altri operatori con CF individuali; il referente non è ripetuto nell'elenco. I non referenti possono aggiornare la sede ma non i dati del referente. La gestione utenti mantiene sincronizzato il profilo legacy del referente.
- Aggiunta `users.tax_code`: migrazione `012_add_user_tax_code.sql` già applicata localmente; su database esistenti applicarla prima dell'aggiornamento se la colonna manca. Lo schema iniziale dei nuovi database include la colonna. Il backfill associa il vecchio CF soltanto all'account indicato da `manager_username` nella stessa attività.
- I CF segnaposto `TEST` sono dati di prova non validi per fatturazione reale e vanno sostituiti con dati verificati. Non attribuire il CF storico al referente attuale senza verifica.
- Registrazione: città, telefono e CF richiesti insieme agli altri campi; per Altro è richiesto anche il nome attività personalizzato. Dicitura Clienti per centri estetici e Iscritti per palestre, aggiornata al cambio sede.
- In precedenza: selettore admin con una sola attività, filtri contatti/appuntamenti per sede, creazione contatti admin vincolata alla sede e correzione di nome/cognome in registrazione.

## Verifiche

- Build Angular, lint PHP, PHPUnit 17 test / 44 assertion, test Playwright mirati e `git diff --check` riusciti. Verificati registrazione del gestore, creazione di un operatore dall'interfaccia, permessi e trasferimento referente, più visualizzazione della scheda.
- Verificato lo schema iniziale utenti con una tabella temporanea. Playwright ha usato Node 22; Node globale 18 non è sufficiente.
- Test mirati senza creare nuove sessioni di checkout Stripe. Pagamento annuale di test e visibilità dei due account CuraPeople verificati in precedenza; non equivale a una verifica completa dei pagamenti in produzione.
- Per riprendere: controllare `git status`, ultimo commit e disponibilità dei servizi; proseguire con i test richiesti dall'utente.

## Da decidere prima della produzione

Prezzi e PayPal sandbox; HTTPS pubblico e nuovi segreti/password; sostituzione dei dati fittizi; revisione legale; firma e pubblicazione Android/iOS e policy store; test automatici rinnovo/cancellazione webhook Stripe. Non attivare pagamenti live senza approvazione.
