# App Android (Capacitor)

## Deep link
Schema: `businessregistry://`. Dopo il checkout Stripe nel browser, Stripe rimanda a
`businessregistry://subscribe?session_id=...` (o `.../paywall?cancelled=1`) e Android riapre l'app.
- L'app invia `native: true` a `api/payments/stripe.php` per ottenere questi URL di ritorno.
- Il listener `appUrlOpen` è in `src/app/app.component.ts`; l'intent filter è in `android/app/src/main/AndroidManifest.xml`.
- PayPal usa ancora il ritorno web (rimandato).

## Build
```
export PATH=$HOME/.local/node20/bin:$PATH
npm run build:mobile      # ng build + cap sync
npx cap open android      # richiede Android Studio + JDK 17
```
Serve un `environment.prod.ts` con `apiUrl` HTTPS pubblico (l'app nativa non raggiunge `localhost`).

## Da fare prima dello store
- JDK 17 e Android SDK (non installati su questa macchina): la build Gradle non è stata provata.
- Test su dispositivo/emulatore del giro completo pagamento → ritorno nell'app.
- Firma (keystore), icone e splash definitivi, `versionCode`.
- Eventuale passaggio a App Links https (richiede dominio e `assetlinks.json`).
- iOS: serve un Mac con Xcode (`npx cap add ios`, poi URL scheme in Info.plist).
- Verificare le regole degli store sui pagamenti esterni per abbonamenti B2B.
