# BusinessRegistry Mobile App

App iOS/Android per la gestione di appuntamenti e servizi. Versione **a pagamento** con Capacitor per distribuzione su App Store e Google Play.

## Caratteristiche

- ✅ **Login sicuro** - Autenticazione con backend PHP
- ✅ **Dashboard intuitiva** - Visualizza appuntamenti e azioni rapide
- ✅ **Gestione appuntamenti** - Prenota e cancella appuntamenti
- ✅ **Paywall integrato** - Sottoscrizione per accesso alle funzioni
- ✅ **In-app purchase** - Pagamenti integrati (RevenueCat)
- ✅ **Offline support** - Sincronizzazione quando disponibile
- ✅ **Responsive design** - Ottimizzato per tutti i dispositivi

## Tech Stack

- **Frontend**: Angular 15 + Ionic 7
- **Mobile**: Capacitor 5
- **Pagamenti**: RevenueCat (easy in-app purchase integration)
- **Backend**: PHP API (BusinessRegistry)
- **Database**: MySQL (tramite backend)

## Setup

### Prerequisiti

```bash
Node.js 18+
npm 9+
iOS 14+ (per compilare per iOS)
Android SDK (per compilare per Android)
```

### Installazione

1. **Installa dipendenze**
```bash
cd app-mobile
npm install
```

2. **Configura Capacitor** (solo la prima volta)
```bash
npm run cap:init
npm run cap:add:ios
npm run cap:add:android
```

3. **Configura le credenziali**

Modifica `src/environments/environment.ts`:
```typescript
export const environment = {
  apiUrl: 'http://localhost:8081', // URL del backend
  // ... altre configurazioni
};
```

### Sviluppo

**Web (development)**:
```bash
npm start
```

**iOS**:
```bash
npm run ios:build
npm run ios:run
```

**Android**:
```bash
npm run android:build
npm run android:run
```

### Compilazione per distribution

**iOS**:
```bash
npm run build:mobile
npx cap open ios
# Apri Xcode e configura signing
```

**Android**:
```bash
npm run build:mobile
npx cap open android
# Apri Android Studio e configura signing
```

## Struttura del progetto

```
src/
├── app/
│   ├── guards/           # Auth guard
│   ├── pages/            # Pagine principali
│   │   ├── login/        # Login page
│   │   ├── paywall/      # Paywall per in-app purchase
│   │   ├── dashboard/    # Dashboard principale
│   │   ├── appointments/ # Gestione appuntamenti
│   │   └── ...
│   ├── services/         # Servizi (Auth, API, Payment)
│   ├── models/           # Interfacce TypeScript
│   └── components/       # Componenti riutilizzabili
├── environments/         # Configurazioni per env
├── assets/              # Risorse (icone, immagini)
└── styles.scss          # Stili globali
```

## Monetizzazione

### RevenueCat Integration

1. **Registrati su RevenueCat**:
   - https://www.revenuecat.com/

2. **Crea i prodotti**:
   - Pro Monthly ($4.99)
   - Pro Yearly ($49.99)

3. **Configura API key** in `environment.ts`:
```typescript
inAppPurchaseConfig: {
  revenueCatApiKey: 'YOUR_API_KEY',
}
```

4. **Configurare App Store / Google Play**:
   - Crea gli stessi SKU / Product IDs in entrambi i store
   - Collega gli account a RevenueCat

## API Integration

L'app si connette al backend PHP mediante le seguenti endpoint:

### Authentication
```
POST /api/auth/login
POST /api/auth/logout
```

### Appointments
```
GET  /api/appointments
POST /api/appointments
DELETE /api/appointments/{id}
```

### Services
```
GET /api/services?gym_id=1
GET /api/gyms
```

### User Profile
```
GET  /api/user/profile
PUT  /api/user/profile
```

## Distribuzione

### App Store (iOS)

1. Registra Developer Account ($99/anno)
2. Crea Certificate e Provisioning Profile
3. Build per production in Xcode
4. Carica versione di test in TestFlight
5. Submit per review

### Google Play (Android)

1. Registra Developer Account ($25 una tantum)
2. Genera signed APK/AAB da Android Studio
3. Carica versione beta
4. Test con closed beta group
5. Publish in production

## Troubleshooting

**Problemi di connessione al backend**:
```bash
# Assicurati che il server PHP sia in esecuzione
# E che l'URL in environment.ts sia corretto
```

**Errori di build iOS**:
```bash
# Pulisci Xcode
rm -rf ~/Library/Developer/Xcode/DerivedData

# Ricrea Capacitor
npm run cap:sync
npm run cap:open:ios
```

**Errori di build Android**:
```bash
# Pulisci gradle
cd android && ./gradlew clean

# Ricrea Capacitor
npm run cap:sync
npm run cap:open:android
```

## Testing

```bash
# Unit tests (mockati)
npm test

# E2E tests (da implementare)
npm run e2e
```

## Licenza

Stesso della applicazione principale (BusinessRegistry)

## Support

Per bug e feature requests, apri una issue nel repository principale.

---

**Versione**: 1.0.0  
**Ultimo update**: Aprile 2026
