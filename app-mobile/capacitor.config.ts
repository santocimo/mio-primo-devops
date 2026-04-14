import { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.businessregistry.app',
  appName: 'BusinessRegistry',
  webDir: 'dist',
  plugins: {
    SplashScreen: {
      launchShowDuration: 0,
    },
    InAppPurchase: {
      // Configurato in runtime per iOS/Android
    },
  },
  server: {
    // In development, punta al server di sviluppo
    url: process.env['IONIC_SERVER_URL'] || undefined,
    cleartext: true,
  },
};

export default config;
