#!/usr/bin/env bash
# Uso: DEVICE_API_URL=http://192.168.1.10:8083 ./scripts/build-android-debug.sh
# Produce android/app/build/outputs/apk/debug/app-debug.apk
set -euo pipefail
cd "$(dirname "$0")/.."
: "${DEVICE_API_URL:?Imposta DEVICE_API_URL (es. http://192.168.1.10:8083)}"
export JAVA_HOME="${JAVA_HOME:-$HOME/.local/jdk17}"
export ANDROID_HOME="${ANDROID_HOME:-$HOME/Android/sdk}"
export PATH="$JAVA_HOME/bin:$HOME/.local/node20/bin:$PATH"

ENV_FILE=src/environments/environment.device.ts
cp "$ENV_FILE" "$ENV_FILE.bak"
trap 'mv "$ENV_FILE.bak" "$ENV_FILE"' EXIT
sed -i "s#__DEVICE_API_URL__#${DEVICE_API_URL}#" "$ENV_FILE"

npx ng build --configuration device
case "$DEVICE_API_URL" in http://*) export ANDROID_ALLOW_HTTP=1 ;; esac
npx cap sync android
(cd android && ./gradlew assembleDebug)
echo "APK: $(pwd)/android/app/build/outputs/apk/debug/app-debug.apk"
