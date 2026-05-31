# Session Handoff

Ultimo aggiornamento: 2026-05-31

## Stato consegna
- Commit locale su master: de9b2d2
- Branch remoto pubblicato: publish/2026-05-31-logout-hardening
- Link PR: https://github.com/santocimo/mio-primo-devops/pull/new/publish/2026-05-31-logout-hardening

## Cosa include
- Hardening globale 401/logout in app mobile.
- Riduzione rumore post-logout (loop 401 e richieste inutili in dashboard).
- Aggiornamenti UI/form Ionic e allineamenti relativi inclusi nel commit salvato.

## Verifica minima alla prossima sessione (60 secondi)
1. git checkout publish/2026-05-31-logout-hardening
2. cd app-mobile && npm run build
3. Smoke test: login -> dashboard -> esci

## Nota importante repository
Questo repository vive nella HOME e contiene anche file runtime/caches.
Per evitare push bloccati su master:
- non fare push diretto da master in questo stato;
- lavorare/pushare su branch dedicati e poi aprire PR.
