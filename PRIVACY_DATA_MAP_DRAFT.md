# BusinessRegistry - mappa privacy dei dati

**Stato:** bozza interna ricavata dall'ispezione del codice il 25 novembre 2026. Non è un'informativa, un DPA o un parere legale e non va pubblicata. Ruoli, basi giuridiche, fornitori, tempi di conservazione e misure vanno verificati prima del lancio con un professionista.

## 1. Perimetro della prima versione

- Confermato dall'utente: accedono a BusinessRegistry le attività e i loro gestori/operatori; gli iscritti non creano un account e non prenotano direttamente online.
- Non ancora deciso: forma e identità legale dell'impresa che offrirà il servizio, hosting di produzione e paese di conservazione, prezzi definitivi e tempi di conservazione.
- Le nuove registrazioni e le nuove assegnazioni di categoria `medical` sono ora rifiutate da API e gestione legacy. Le attività già classificate `medical` restano leggibili e possono mantenere la categoria esistente; non sono stati eliminati o modificati dati legacy. Prima del lancio va verificato se esistono attività di questo tipo e se trattano dati sanitari.

## 2. Dati rilevati nel codice

| Flusso | Dati presenti o richiesti | Uso tecnico visibile |
|---|---|---|
| Registrazione attività/referente | Nome e categoria dell'attività, indirizzo, città, telefono; nome e cognome del referente, codice fiscale, email, username e password | Creazione dell'attività e dell'account gestore; avvio della prova; la password viene salvata come hash |
| Account operatori | Nome, email, username, ruolo, codice fiscale, identificativo dell'attività e hash password | Accesso e gestione degli account dell'attività |
| Anagrafica iscritti | Nome e cognome; codice fiscale, data e comune di nascita, sesso, indirizzo e recapito facoltativi | Gestione dell'iscritto; i dati necessari vengono usati per calcolare/controllare il codice fiscale |
| Iscritti - esportazione | ID, nome, cognome, codice fiscale, data e comune di nascita, indirizzo, recapito e sesso | Esportazione CSV dall'area contatti |
| Servizi | Nome, categoria, descrizione, fornitore/nome referente, durata, capienza e prezzo | Gestione dei servizi dell'attività |
| Appuntamenti | Collegamento al servizio e, se presente, all'iscritto; nome/email, orario UTC e fuso d'origine, stato e note libere | Pianificazione e gestione degli appuntamenti |
| Abbonamenti e pagamenti | Attività e referente di fatturazione, piano, stato, date, importo/valuta e identificativi Stripe/PayPal di abbonamento, cliente o pagamento | Attivazione, rinnovo e cancellazione degli abbonamenti |
| Audit amministrativo | ID/ruolo admin, azione, risorsa e ID risorsa, attività selezionata, stato HTTP, data di inizio e completamento | Traccia delle richieste amministrative su risorse dell'app; il codice prevede la rimozione dei record oltre 90 giorni |
| Log tecnici | Errori applicativi; contenuto, IP e scadenza dei log del web server/hosting non determinati dal codice esaminato | Diagnosi e sicurezza dell'infrastruttura; da verificare con il provider di produzione |

Le note degli appuntamenti sono testo libero; l'interfaccia avverte di non inserire dati sanitari o informazioni personali non necessarie. Non è stata individuata una funzione di prenotazione pubblica per gli iscritti.

Nel flusso di creazione contatti, solo nome e cognome sono obbligatori sia nell'interfaccia sia nell'API. Codice fiscale, data e comune di nascita, sesso, indirizzo e recapito sono facoltativi; quando vengono inseriti, codice fiscale e data vengono validati. Il calcolo automatico del codice fiscale richiede i dati anagrafici necessari, ma non è più un requisito per salvare il contatto. Non è emerso un uso dei dati degli iscritti per fatturazione. La ricerca del comune interroga l'endpoint dell'app e la tabella locale; non è emerso un servizio esterno di geocodifica. I valori già presenti non vengono cancellati automaticamente.

## 3. Ruoli da confermare

Impostazione di lavoro, da convalidare legalmente:

- L'attività cliente decide perché raccoglie e usa i dati dei propri iscritti e operatori; normalmente è il titolare per questi trattamenti.
- Il fornitore di BusinessRegistry tratta tali dati per fornire il software, secondo le istruzioni documentate dell'attività; normalmente opera come responsabile del trattamento.
- Il fornitore della piattaforma avrà trattamenti propri per account, fatturazione, assistenza, sicurezza e log amministrativi e potrà essere titolare per tali finalità.
- Gestore e operatori dell'attività accedono ai dati per il lavoro affidato loro dall'attività. L'attività deve informarli e dare istruzioni coerenti con i loro compiti.
- L'amministratore della piattaforma ha capacità tecnica globale. L'accesso ai dati di una specifica attività per assistenza dovrebbe essere limitato a una richiesta/motivazione, circoscritto nel tempo e registrato.

L'assegnazione effettiva dei ruoli dipende da chi determina finalità e mezzi di ciascun trattamento e va riportata nei contratti.

## 4. Accessi e visibilità attuali

- Le API di iscritti, appuntamenti e servizi verificano il ruolo e delimitano i dati operativi non-admin all'attività associata all'account.
- Gestore/referente e operatori possono accedere ai dati operativi della propria attività; le funzioni di gestione degli account sono più limitate rispetto alla consultazione.
- L'admin può consultare dati di più attività, coerentemente con la funzione globale attuale.
- L'interfaccia mostra l'esportazione CSV dei contatti solo all'admin e l'API applica lo stesso controllo lato server (403 per gli altri ruoli). L'API richiede anche una sede esplicita, così l'export non include tutte le attività per errore.
- L'utente ha richiesto la visibilità dei dati degli iscritti e dello staff per gestire l'attività. Prima del lancio va comunque applicata la minimizzazione per singolo campo: in particolare verificare la necessità di mostrare agli operatori il codice fiscale degli altri operatori.
- L'audit attuale riguarda le richieste amministrative, non tutte le consultazioni degli operatori. Non registra la motivazione di assistenza né l'indirizzo IP. La finestra tecnica di 90 giorni non costituisce da sola una decisione sulla conservazione legale.

## 5. Fornitori e trasferimenti

**Rilevati nel codice:**

- Stripe: checkout ricorrente ospitato dal provider e webhook; l'applicazione conserva ID di cliente/abbonamento/pagamento e stato/importo/valuta. Non sono presenti campi carta nel database descritto.
- PayPal: autorizzazione e gestione dell'abbonamento tramite provider; l'applicazione conserva identificativi del provider, piano, stato, pagamento e importo.
- Database relazionale MariaDB e API PHP; l'ambiente attuale è Docker locale. Il provider e la regione di hosting di produzione non sono stati scelti.

**Da verificare prima di pubblicare:**

- contratti, ruoli privacy, sub-responsabili e paesi di trattamento di Stripe, PayPal e dell'hosting;
- log e backup del provider, localizzazione, accessi di assistenza, tempi di cancellazione e procedure di ripristino;
- eventuali fornitori di email, analytics, crash reporting, notifiche o altri servizi aggiunti al sito/app;
- cookie e tracciamenti del futuro sito pubblico e del frontend web. Nell'app e nelle dipendenze esaminate non è emerso un SDK esplicito di analytics/advertising; questo non sostituisce la verifica dell'infrastruttura e del sito pubblico.

## 6. Conservazione e cancellazione

- Esiste una funzione/script che elimina l'audit amministrativo oltre 90 giorni. La documentazione di sicurezza raccomanda di schedularlo ogni giorno; non è stata verificata la schedulazione effettiva sull'ambiente di produzione.
- La cancellazione dell'account operatore può rimuovere l'account senza eliminare l'intera attività e le relative anagrafiche.
- La cancellazione dell'attività dal referente può eliminare utenti e iscritti collegati; va verificato con test isolati cosa accade a servizi, appuntamenti, pagamenti, audit e backup.
- Non risultano definiti nell'app tempi complessivi di conservazione dei dati dell'attività inattiva, esportazione prima della chiusura o scadenza dei backup.

Non promettere la cancellazione immediata e completa finché il comportamento di database, log, provider e backup non è stato verificato e documentato.

## 7. Azioni consigliate prima del lancio

1. I campi identificativi aggiuntivi degli iscritti sono ora facoltativi in interfaccia e API. Mantenere questa minimizzazione, salvo che l'attività documenti una necessità concreta e legittima.
2. Limitare la visibilità del codice fiscale degli operatori; mantenere nome/ruolo e recapito di lavoro solo nella misura necessaria alle attività.
3. La categoria `medical` è bloccata per nuove attività e cambi categoria; verificare e gestire eventuali attività legacy prima del lancio.
4. L'export CSV è admin-only e richiede una sede esplicita; mantenere test di autorizzazione e verificare che l'admin selezioni la sede corretta prima dell'esportazione.
5. Definire un flusso di assistenza: richiesta dell'attività, ambito e durata dell'accesso admin, motivazione e traccia.
6. Scegliere hosting e area geografica; ottenere la lista dei fornitori e dei sub-responsabili e verificare contratti e trasferimenti.
7. Stabilire tempi di esportazione, cancellazione e backup, poi allineare database, interfaccia e documenti.
8. Preparare separatamente informativa del fornitore, condizioni B2B dell'abbonamento e DPA da offrire alle attività; farli revisionare prima della pubblicazione.
9. Completare i blocchi tecnici di `DEPLOY_CHECKLIST.md` prima di esporre l'app autenticata o attivare pagamenti reali.

## 8. Riferimenti usati

- [Garante Privacy - principi fondamentali del trattamento](https://www.garanteprivacy.it/home/principi-fondamentali-del-trattamento)
- [EDPB - Guidelines 07/2020, titolare e responsabile del trattamento](https://www.edpb.europa.eu/system/files/2023-10/EDPB_guidelines_202007_controllerprocessor_final_en.pdf)
- [Mindbody - Privacy Annex](https://www.mindbodyonline.com/company/legal/terms-of-service/privacy-annex)
- [Gymdesk - Privacy Notice](https://gymdesk.com/page/privacy)
- [Bookingmood - Data Processing Agreement](https://www.bookingmood.com/en-GB/dpa)

I modelli commerciali sono stati usati solo per confrontare la struttura dei documenti, non come testi da copiare o come prova che le stesse clausole siano appropriate per BusinessRegistry.
