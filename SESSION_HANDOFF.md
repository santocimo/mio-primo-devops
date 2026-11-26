# Session handoff

Aggiornato il 2026-11-26. Le note qui sotto sono lo stato più recente e prevalgono sulle note storiche successive. Per la ripresa operativa vedere anche `RIPRENDI_QUI.md` e `DEPLOY_CHECKLIST.md`.

## Routine

Aggiornare sempre questo handoff al termine del lavoro con attività completate, verifiche, stato di commit/deploy e prossimo passo. Non inserire segreti o dati personali.

## Ultimo checkpoint

- Il 26 novembre sono state committate e pushate le modifiche di preparazione deploy e migrazione frontend nel commit `95988d1` (`Prepare production deploy and modernize frontend`) sul branch `work/clean-app-2026-10-04`. Il push è riuscito. Questo handoff viene aggiornato e pushato separatamente per registrare lo stato finale.
- Verifiche del checkpoint: build Angular di produzione e `npm audit` riusciti (0 vulnerabilità); PHPUnit 40 test / 67 asserzioni, 7 saltati perché dipendono da un DB di test isolato; `composer validate`, PHP lint e `git diff --check` riusciti. Il wrapper Android Gradle 8.13 è stato verificato con JDK 21; non è stata prodotta l'APK perché manca Android SDK. Le immagini Docker, la configurazione Compose e gli smoke test web/Apache erano già stati verificati nel checkpoint precedente.
- L'anteprima web continua su `http://localhost:4200`; la schermata profilo è stata aperta e la build passa, ma l'utente deve ancora confermare i flussi manuali. È solo un server di sviluppo, esposto sulla porta 4200: non inserire dati reali.
- Nessun deploy pubblico, dominio, pagamento live o migrazione del database locale è stato eseguito. La checklist di produzione resta in `DEPLOY_CHECKLIST.md`; l'APK Android e i prerequisiti legali/infrastrutturali sono ancora da completare.
- Le modifiche privacy, i relativi test e la bozza della mappa privacy sono nel commit `f8914ec` (`Strengthen privacy and contact data handling`), pushato su `origin/work/clean-app-2026-10-04`. Non sono state distribuite in produzione.
- Commit `be733e5` (`Implement timezone-aware appointments and access tests`) è stato pushato su `origin/work/clean-app-2026-10-04` su richiesta dell'utente; il worktree era pulito dopo il push.
- I quattro appuntamenti legacy senza fuso sono stati convertiti in `Europe/Rome` dopo l'autorizzazione dell'utente. Non restano righe con `scheduled_timezone IS NULL`; il record legacy verificato appare alle 10:00 ora italiana e alle 10:00 ora di inserimento. Backup pre-migrazione: `/home/santo/backups-db/mio_database_pre_legacy_timezone_20261125_105231.sql`.
- Gli admin vedono ora italiana e fuso d'inserimento nelle pagine appuntamenti; operatori/gestori vedono il proprio orario locale. Il registro accessi admin mostra solo l'ora italiana.
- La revisione e i test di autorizzazione hanno aggiunto test HTTP su MariaDB usa-e-getta per admin/SUPER, gestore referente, operatori italiani e legacy, isolamento fra due attività, endpoint admin-only e abbonamenti scaduti. Corretti due casi: il ruolo `OPERATOR` legacy era respinto da `/api/users`; aggiornamenti/cancellazioni di contatti fuori attività restituivano successo pur non modificando righe.
- L'utente ha scelto di mantenere visibili agli operatori della stessa attività i dati dello staff già mostrati dalla scheda (inclusi email e codici fiscali).
- Verifica completa: 34 test / 126 asserzioni; PHP lint e `git diff --check` superati. Test eseguiti su un container MariaDB usa-e-getta poi rimosso; database attivo non toccato.
- Attenzione deploy: le correzioni RBAC in `be733e5` non sono state ancora distribuite. Il precedente deploy backend timezone/audit era stato eseguito separatamente. Non ricreare né riavviare il database o il volume `santo_db_data`.

## Prossimo passo

Attendere la conferma dell'utente dopo la prova manuale dell'anteprima su `http://localhost:4200`. Se emergono problemi, correggerli in un nuovo commit; non aggiornare/ricreare database o volume `santo_db_data`. Per la pubblicazione, seguire `DEPLOY_CHECKLIST.md` e ottenere prima le decisioni mancanti su hosting, dominio, segreti e requisiti legali.

## Pubblicazione su dominio e revisione legale (25 novembre 2026)

- L'utente vuole preparare la pubblicazione pubblica e le verifiche legali/privacy. Ha confermato che la prima versione sarà usata solo da attività e operatori: gli iscritti non si registrano né prenotano direttamente online. Forma dell'impresa e identità legale sono ancora da definire; hosting di produzione non scelto. Rivalutare prenotazioni/pagamenti pubblici solo se il prodotto estenderà il perimetro.
- L'utente chiarisce che referente e operatori devono poter consultare i dati degli iscritti e dello staff per gestire l'attività. Trattare questo come requisito funzionale da tradurre in permessi proporzionati, non come autorizzazione indiscriminata: raccomandazione iniziale, referente con funzioni gestionali; operatore con i dati operativi necessari; evitare la visibilità del CF degli altri operatori salvo necessità documentata. L'amministratore della piattaforma deve usare accessi ai dati tenant per assistenza solo su necessità, con motivazione, ambito limitato e audit.
- Raccomandazione di lancio da validare: SaaS inizialmente B2B per attività, senza prenotazione/pagamento pubblico da parte dei clienti finali. Per i dati operativi di iscritti/operatori l'attività cliente è normalmente titolare e la piattaforma responsabile; la piattaforma resta titolare autonomo per account, fatturazione, sicurezza e log propri. Formalizzare ruoli e istruzioni nel DPA.
- Confrontati i modelli pubblici di [Mindbody](https://www.mindbodyonline.com/company/legal/terms-of-service/privacy-annex), [Gymdesk](https://gymdesk.com/page/privacy) e [Bookingmood](https://www.bookingmood.com/en-GB/dpa), senza copiarne i testi. Pattern utile: informativa per il trattamento proprio del SaaS distinta dal DPA per i dati dei clienti dell'attività; descrivere ruoli, istruzioni, categorie di dati, sub-responsabili/trasferimenti, richieste degli interessati, sicurezza, incidenti, esportazione e cancellazione.
- Creato [PRIVACY_DATA_MAP_DRAFT.md](/home/santo/progetti/mio-primo-devops/PRIVACY_DATA_MAP_DRAFT.md), documento interno non pubblicabile con dati/categorie, accessi, fornitori e decisioni da confermare.
- Modificata la gestione dei contatti: solo nome e cognome sono obbligatori; CF, data/comune di nascita, sesso, indirizzo e recapito sono facoltativi. Se inseriti, CF e data vengono validati. Il calcolo CF automatico resta disponibile quando ci sono i dati necessari; i dati già presenti non sono stati cancellati.
- Protetto l'export CSV contatti anche lato API: utenti non-admin ricevono 403 e l'admin deve indicare una sede; l'export non può includere tutte le attività per errore. Le prove d'integrazione verificano autorizzazione e isolamento.
- Bloccata la categoria `medical` per nuove registrazioni e nuove assegnazioni via API e gestione legacy. Le attività già classificate medical sono conservate e possono mantenere la categoria; controllare eventuali record legacy prima del lancio.
- Verificato il flusso di ricerca comuni: usa l'endpoint dell'app e la tabella locale, senza servizio esterno individuato.
- Lo script di pulizia audit ha retention di 90 giorni; Compose non pianifica la pulizia e l'ambiente di produzione non è scelto, quindi la schedulazione va configurata e verificata sull'hosting.
- Validazione delle modifiche: suite PHP 38 test / 154 asserzioni passata; integrazione autorizzazioni eseguita su MariaDB temporaneo isolato e container rimosso; PHP lint, build Angular di produzione e `git diff --check` superati. Il test Playwright E2E è stato aggiornato e la sintassi transpilata, ma non eseguito: l'ambiente ha Node 18 mentre Playwright richiede Node 20+. Nessun dato di produzione toccato e nessun deploy eseguito; commit e push effettuati come indicato nell'ultimo checkpoint.
- Il cleanup audit ha retention di 90 giorni; lo script esiste e la documentazione raccomanda una schedulazione giornaliera, ma `docker-compose.yml` non la configura e l'ambiente di produzione non è stato scelto, quindi la schedulazione effettiva non è verificabile.
- La bozza [legal-content.ts](/home/santo/progetti/mio-primo-devops/app-mobile/src/app/pages/legal/legal-content.ts) è incompleta e l'interfaccia la marca esplicitamente come bozza. Mancano almeno identità/contatti legali verificati, hosting e localizzazione, elenco completo di fornitori/sub-responsabili, tempi di conservazione/cancellazione e flusso per richieste sui dati degli iscritti. Non pubblicarla come testo definitivo.
- Dall'implementazione risultano account di attività/operatori (nome, email, username, codice fiscale), anagrafiche iscritti (nome, cognome, codice fiscale, data e comune di nascita, sesso, indirizzo e recapito), appuntamenti (nome/email, orario, stato e note) e dati di piano/pagamento. La UI avverte di non inserire dati sanitari nelle note; verificare che l'avviso e le istruzioni agli operatori siano adeguati.
- I campi anagrafici aggiuntivi degli iscritti sono facoltativi; codice fiscale e data vengono validati solo quando compilati. Il CF automatico richiede dati sufficienti. L'attività deve comunque decidere e documentare quali informazioni le servono; il cambiamento non elimina i valori già archiviati.
- La cancellazione dell'account può eliminare l'intera attività e i suoi contatti per il referente di fatturazione, mentre per un account operatore può limitarsi alla rimozione dell'account. Definire e documentare tempi di conservazione, esportazione e cancellazione dei backup in coerenza con il comportamento reale.
- Prossime decisioni necessarie per redigere bozze personalizzate da far revisionare: forma e identità dell'impresa; provider/paese hosting e sub-responsabili; prezzi e condizioni finali; tempi di conservazione. Il deploy resta bloccato dai punti tecnici elencati in `DEPLOY_CHECKLIST.md`.

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

- Ultimo risultato: PHPUnit 38 test / 154 asserzioni, incluse prove HTTP su MariaDB temporaneo, PHP lint, build Angular di produzione e `git diff --check` riusciti. Il test Playwright E2E non è stato eseguito perché Node 18 è inferiore al requisito Playwright Node 20+; la specifica è stata controllata per sintassi.
- I test HTTP RBAC usano `RBAC_TEST_DB_*` e richiedono `RBAC_TEST_DB_CONFIRM=YES`; la CI usa il servizio MariaDB `test_db`. Non puntare mai queste variabili al database reale.
- Verificato lo schema iniziale utenti con una tabella temporanea. Una precedente verifica Playwright ha usato Node 22; il test E2E aggiornato per questa modifica resta non eseguito, perché il Node globale 18 non soddisfa il requisito.
- Test mirati senza creare nuove sessioni di checkout Stripe. Un pagamento annuale di test era stato verificato in precedenza; non equivale a una verifica completa dei pagamenti in produzione.
- Per riprendere: controllare `git status`, commit corrente e disponibilità dei servizi; ricordare che gli ultimi fix RBAC non sono ancora in produzione.

## Da decidere prima della produzione

Consultare `DEPLOY_CHECKLIST.md`: URL API produzione, esclusione `.env` dall'immagine senza perdere configurazione, valutazione rotazione segreti potenzialmente esposti, HTTPS, backup con ripristino provato, prezzi e PayPal sandbox, revisione legale, firma e pubblicazione Android/iOS, policy store e test webhook Stripe. Non attivare pagamenti live senza approvazione.
