# Setup Completo App Mobile

Guida step-by-step per impostare l'app mobile iOS/Android scaricabile da app store.

## Phase 1: Setup Locale (Development)

### 1.1 Installazione dipendenze

```bash
cd app-mobile

# Installa Node modules
npm install

# Verifica versioni
node --version  # v18+
npm --version   # 9+
```

### 1.2 Test in browser

```bash
# Avvia server di dev
npm start

# L'app sarà disponibile a http://localhost:4200
# Accedi con un utente creato nel database.
```

### 1.3 Configurare endpoint API

Modifica `src/environments/environment.ts`:
```typescript
export const environment = {
  production: false,
   apiUrl: 'http://localhost:8083',  // Indirizzo del tuo backend
};
```

## Phase 2: Setup Capacitor

### 2.1 Inizializza Capacitor

```bash
npm run cap:init

# Quando chiede:
# App name: BusinessRegistry
# App Package: com.businessregistry.app
# Web dir: dist
```

### 2.2 Aggiungi piattaforme

```bash
npm run cap:add:ios
npm run cap:add:android
```

Le cartelle native sono generate localmente e sono escluse da Git. Dopo aver clonato il progetto, aggiungi entrambe le piattaforme e sincronizza gli asset con `npm run build:mobile`.

**Requisiti**:
- **iOS**: Xcode 14+ installato (macOS)
- **Android**: Android Studio + Android SDK

Il backend di produzione deve essere pubblicato su un URL HTTPS prima di compilare una release. `src/environments/environment.prod.ts` contiene ancora un dominio segnaposto: non distribuire build che lo usano.

## Phase 3: Configurazione iOS

### 3.1 Certificati di desarrollador

1. Accedi a [Apple Developer Account](https://developer.apple.com)
2. Crea un "Certificate":
   - Seleziona "iOS App Development"
   - Scarica il certificato e imposta in Keychain
3. Crea un "Identifier":
   - com.businessregistry.app
4. Crea un "Provisioning Profile":
   - Development
   - Collega il certificato e l'identifier

### 3.2 Build e test

```bash
# Apri Xcode
npm run cap:open:ios

# In Xcode:
# 1. Seleziona "BusinessRegistry" nella sidebar
# 2. General > Signing & Capabilities
# 3. Seleziona il team (il tuo account Developer)
# 4. Run su device collegato (Cmd + R)
```

### 3.3 Configurazione per App Store

Per distribuzione:

1. Crea ulteriori certificati (App Store)
2. Crea App Store Provisioning Profile
3. In Xcode:
   - Seleziona "Any iOS Device (arm64)" come target
   - Product > Archive
   - Distribuisci su App Store

## Phase 4: Configurazione Android

### 4.1 Setup Android Studio

1. Installa [Android Studio](https://developer.android.com/studio)
2. Apri `app-mobile/android` con Android Studio
3. Lascia che Android Studio scarichi SDK necessari

### 4.2 Genera keystore

```bash
# Crea chiave di firma
keytool -genkey -v -keystore release.jks \
  -keyalg RSA -keysize 2048 -validity 10000 \
  -alias android-release

# Salva in app-mobile/android/app/release.jks
```

### 4.3 Configura signing

Modifica `app-mobile/android/app/build.gradle`:

```gradle
android {
    signingConfigs {
        release {
            storeFile file('release.jks')
            storePassword project.hasProperty('KEYSTORE_PASSWORD') ? KEYSTORE_PASSWORD : ''
            keyAlias project.hasProperty('KEY_ALIAS') ? KEY_ALIAS : ''
            keyPassword project.hasProperty('KEY_PASSWORD') ? KEY_PASSWORD : ''
        }
    }
    
    buildTypes {
        release {
            signingConfig signingConfigs.release
        }
    }
}
```

### 4.4 Build APK/AAB

```bash
# Apri Android Studio
npm run cap:open:android

# In Android Studio:
# 1. Build > Build Bundle(s) / APK(s)
# 2. Seleziona "Release"
# 3. Genera file (.aab per Play Store, .apk per testing)
```

## Phase 5: Prodotto e pagamenti

I pagamenti sono attualmente disattivati. Prima di riattivarli nelle app distribuite dagli store, verificare le regole Apple e Google applicabili al prodotto, al tipo di accesso digitale e ai paesi di distribuzione. Il checkout web Stripe/PayPal non va considerato automaticamente approvato per acquisti in-app. I prezzi presenti nel backend sono provvisori e non sono prezzi commerciali approvati.

Prima di promuovere l'app, validare l'esperienza con gestori di palestre, centri estetici, studi e altre attività, senza restringere il prodotto a una singola categoria. Osservare se ciascuno riesce a configurare la propria attività, aggiungere servizi e iscritti/clienti e fissare un appuntamento senza assistenza; raccogliere i blocchi e correggere quelli ricorrenti prima di aggiungere funzioni specifiche di settore.

## Privacy dei dati dei clienti

Nel modulo iscritti/clienti sono obbligatori nome, cognome, codice fiscale, data e comune di nascita e sesso. Selezionando un comune dai risultati, l'app calcola il codice fiscale, che resta modificabile e va verificato dall'utente. Indirizzo e telefono sono facoltativi. Raccogliere i dati solo quando servono davvero all'attività e spiegare ai clienti finalità e tempi di conservazione nell'informativa privacy.

Per i database esistenti, applicare la migration `migrations/011_make_contact_gender_optional.sql` per rimuovere il valore predefinito "M" dai nuovi record senza modificare i dati già salvati.

## Phase 6: Distribuzione App Store

### 6.1 Prepara per submission

```bash
# Incrementa versionCode
# In src/app/app.component.ts versione: "1.0.0"

# Build per produzione
npm run build:mobile

# Apri Xcode
npm run cap:open:ios
```

### 6.2 App Store Connect

1. Accedi a [App Store Connect](https://appstoreconnect.apple.com)
2. Pubblica e collega un'informativa privacy verificata, coerente con dati raccolti, finalità, fornitori e tempi di conservazione.
3. Verifica il percorso in-app di cancellazione nel Profilo e il percorso web `/account-deletion`.
4. Compila le dichiarazioni privacy e prepara account dimostrativi e istruzioni per la review.
5. Verifica le regole di pagamento applicabili prima di abilitare acquisti o link a checkout esterni.
6. Prepara screenshots, descrizione accurata e metadati.
7. Invia per review solo dopo prove su dispositivi e backend di produzione.

**Cose importanti**:
- L'esperienza deve offrire valore e funzionalità sufficienti oltre a una semplice visualizzazione del sito.
- Le dichiarazioni privacy devono corrispondere al comportamento effettivo dell'app e degli SDK inclusi.

## Phase 7: Distribuzione Google Play

### 7.1 Prepara per submission

```bash
# Build AAB (bundle)
# In Android Studio: Build > Build Bundle(s)

# Assicurati che sia firmato con la release key
```

### 7.2 Google Play Console

1. Accedi a [Play Console](https://play.google.com/console)
2. Privacy Policy: Aggiungi URL
3. Pubblica l'app web e verifica che la risorsa `/account-deletion` sia raggiungibile anche senza installare l'app.
4. Compila Data safety, inclusi i dettagli sulla cancellazione, e il questionario di classificazione dei contenuti.
5. Seleziona un pubblico e categorie che rappresentino tutte le attività effettivamente supportate.
6. Verifica i requisiti correnti sul target API prima della build di rilascio.
7. Verifica le regole di pagamento applicabili prima di abilitare acquisti o link a checkout esterni.

### 7.3 Upload AAB

1. Internal Testing:
   - Upload AAB
   - Invita utenti interni
   - Testa completamente
   
2. Closed Testing:
   - Invited testers (es. 50 utenti beta)
   - Raccogli feedback
   
3. Open Testing:
   - Public beta (chiunque può testare)
   - 2-3 giorni di test

4. Production:
   - Upload final AAB solo dopo aver soddisfatto i requisiti API target e privacy correnti.
   - Scrivi un change log accurato.

## Release blockers verificati (2026-10)

- La configurazione Capacitor è alla versione 5 e lo scaffold Android generato usa `targetSdkVersion = 33`. Google Play richiede API 36 per nuove app e aggiornamenti dal 31 agosto 2026. Aggiornare Capacitor/toolchain Android e testare le modifiche di comportamento prima della submission.
- L'ambiente disponibile qui usa Node 18; il Playwright installato dichiara Node 20 o superiore. I test browser e la validazione nativa completa restano da eseguire con una toolchain supportata.
- L'audit npm delle sole dipendenze di produzione rileva 8 advisory Angular (4 high e 4 moderate); pianificare l'aggiornamento coordinato di Angular, Ionic e Capacitor e rieseguire l'audit prima della release.
- I progetti Android/iOS possono essere generati localmente con Capacitor, ma iOS richiede macOS/Xcode e Android richiede Android Studio/SDK per build, firma e test.
- Il flusso self-service per la cancellazione account/attività è implementato; definire con consulenza privacy tempi di conservazione delle copie di backup e dei record finanziari prima del lancio.
   - Submit for review

### 7.4 Attendi approval

- 2-4 ore solitamente (veloce di Apple)
- Se rifiutata, correggere e ri-inviare

## Phase 8: Post-Launch

### 8.1 Marketing

- Pubblica link App Store e Google Play
- Comunica sui social
- Chiedi reviews agli utenti

```
App Store: https://apps.apple.com/app/[BUNDLE_ID]
Google Play: https://play.google.com/store/apps/details?id=com.businessregistry.app
```

### 8.2 Updates

Per future versioni:
```bash
# Modifica versionCode in Xcode/Android Studio
# Build e test
npm run build:mobile
npm run cap:sync

# Submit nuova versione agli app store
```

### 8.3 Monitoraggio

- Crash Reports: App Store Connect e Play Console
- Reviews: Rispondi alle recensioni degli utenti

## Troubleshooting

**Problema**: "Signing certificate not found"
```bash
# Soluzione: Ricrea certificati in Xcode
# Xcode > Preferences > Accounts > Manage Certificates
```

**Problema**: "Android SDK not found"
```bash
# Soluzione: 
npm run cap:sync
npm run cap:open:android
# In Android Studio: Configure > SDK Manager
```

**Problema**: App non si connette al backend
```bash
# Controlla:
# 1. L'URL in environment.ts è corretto
# 2. Il backend è in esecuzione
# 3. CORS è configurato nel backend
# 4. Usa HTTPS in produzione (non HTTP)
```

**Problema**: Il checkout non è disponibile
```bash
# Verifica:
# Verifica che il backend sia configurato con un provider supportato.
# I pagamenti sono disattivati finché non viene approvato il modello e
# verificata la compatibilità con le regole dello store di distribuzione.
```

## Timeline Stimate

- **Setup locale**: 1 giorno
- **Capacitor setup**: 1 giorno
- **iOS configuration**: 2-3 giorni (Certificati)
- **Android configuration**: 1-2 giorni
- **Testing**: 3-5 giorni
- **Submission**: 1 giorno
- **Review & approval**: 3-7 giorni

**Total**: ~2-3 settimane

---

**Domande?** Controlla API_SETUP.md per dettagli sugli endpoint.
