# App Android (Capacitor)

## Deep link
Schema: `businessregistry://`. Dopo il checkout Stripe nel browser, Stripe rimanda a
`businessregistry://subscribe?session_id=...` (o `.../paywall?cancelled=1`) e Android riapre l'app.
- L'app invia `native: true` a `api/payments/stripe.php` per ottenere questi URL di ritorno.
- Il listener `appUrlOpen` è in `src/app/app.component.ts`; l'intent filter è in `android/app/src/main/AndroidManifest.xml`.
- PayPal usa ancora il ritorno web (rimandato).

## Build APK di prova (provato: BUILD SUCCESSFUL)
Strumenti in home, senza sudo: JDK 17 in `~/.local/jdk17`, SDK in `~/Android/sdk`.
```
DEVICE_API_URL=http://<IP-del-PC>:8083 ./scripts/build-android-debug.sh
```
L'APK è in `android/app/build/outputs/apk/debug/app-debug.apk`. Con URL http la build abilita il mixed content (solo prove).
Nota WSL2: il telefono non raggiunge direttamente l'IP di WSL; serve l'IP Windows con port proxy, oppure un dominio HTTPS pubblico.

## Build release
```
export PATH=$HOME/.local/node20/bin:$PATH
npm run build:mobile      # ng build + cap sync
npx cap open android      # richiede Android Studio + JDK 17
```
Serve un `environment.prod.ts` con `apiUrl` HTTPS pubblico (l'app nativa non raggiunge `localhost`).

## Da fare prima dello store
- Test su dispositivo/emulatore del giro completo pagamento → ritorno nell'app.
- Firma (keystore), icone e splash definitivi, `versionCode`.
- Eventuale passaggio a App Links https (richiede dominio e `assetlinks.json`).
- iOS: serve un Mac con Xcode (`npx cap add ios`, poi URL scheme in Info.plist).
- Verificare le regole degli store sui pagamenti esterni per abbonamenti B2B.
