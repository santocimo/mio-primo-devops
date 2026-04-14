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
# Login: admin / admin123
```

### 1.3 Configurare endpoint API

Modifica `src/environments/environment.ts`:
```typescript
export const environment = {
  production: false,
  apiUrl: 'http://localhost:8081',  // Indirizzo del your backend
  inAppPurchaseConfig: {
    revenueCatApiKey: 'YOUR_DEV_API_KEY'
  }
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

**Requisiti**:
- **iOS**: Xcode 14+ installato (macOS)
- **Android**: Android Studio + Android SDK

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

## Phase 5: In-App Purchase (RevenueCat)

### 5.1 Registrazione

1. Vai su [RevenueCat.com](https://www.revenuecat.com)
2. Registrati e crea nuovo progetto
3. Seleziona piattaforme: iOS e Android

### 5.2 Configura prodotti

Crea questi prodotti in RevenueCat:
- **ID**: businessregistry_monthly
  - Prezzo: €4.99
  - Tipo: Subscription (monthly)
- **ID**: businessregistry_yearly
  - Prezzo: €49.99
  - Tipo: Subscription (yearly)

### 5.3 Collega con App Store / Google Play

**App Store**:
1. In RevenueCat, aggiungi API key (in App Store Connect > Keys > Subscription Key)
2. Crea gli stessi prodotti in App Store Connect
3. RevenueCat sincronizzerà automaticamente

**Google Play**:
1. Aggiungi in RevenueCat la Service Account key di Google Play
2. Crea gli stessi prodotti come In-App Products in Play Console
3. RevenueCat sincronizzerà automaticamente

### 5.4 Configura API key nell'app

Modifica `src/environments/environment.prod.ts`:
```typescript
inAppPurchaseConfig: {
  revenueCatApiKey: 'pk_prod_YOUR_API_KEY'
}
```

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
2. Privacy Policy: Aggiungi URL della privacy policy
3. Pricing: Seleziona paesi e prezzo
4. In-App Purchases: Configura i prodotti di ReneveCAT
5. Screenshots e descrizione
6. Invia per review

**Cose importanti**:
- Privacy Policy è OBBLIGATORIA
- App deve funzionare senza connessione (almeno in parte)
- In-app purchase deve essere chiaramente visibile

### 6.3 Attendi review

- 24-48 ore solitamente
- Se rifiutata, Apple fornisce motivo
- Correggi e ri-invia se necessario

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
3. Target audience: Seleziona (es. "Fitness")
4. Content rating questionnaire
5. Pricing: Seleziona paesi e prezzo
6. In-App Products: Aggiungi prodotti RevenueCat

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
   - Upload final AAB
   - Scrivi change log
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

- RevenueCat Dashboard: Monitora entrate
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

**Problema**: In-app purchase non funziona
```bash
# Verifica:
# 1. API key RevenueCat è corretta
# 2. Prodotti esistono in App Store/Play Store
# 3. Device/Simulator ha account test configurato
```

## Timeline Stimate

- **Setup locale**: 1 giorno
- **Capacitor setup**: 1 giorno
- **iOS configuration**: 2-3 giorni (Certificati)
- **Android configuration**: 1-2 giorni
- **RevenueCat setup**: 1 giorno
- **Testing**: 3-5 giorni
- **Submission**: 1 giorno
- **Review & approval**: 3-7 giorni

**Total**: ~2-3 settimane

---

**Domande?** Controlla API_SETUP.md per dettagli sugli endpoint.
