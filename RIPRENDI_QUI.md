# Promemoria da incollare a inizio sessione (aggiornato 2026-10-07)

Progetto BusinessRegistry. Leggi prima SESSION_HANDOFF.md nel repo.

- Repo vero: /home/santo/progetti/mio-primo-devops (NON /home/santo, che non e' un repo git).
- Branch di lavoro: work/clean-app-2026-10-04 (su GitHub santocimo/mio-primo-devops). Mai lavorare/pushare su master.
- Prima di iniziare: git status, git branch --show-current, git log --oneline -3; confronta con `git ls-remote origin work/clean-app-2026-10-04`.
- A fine sessione: commit + push del branch (scripts/checkpoint_session.sh) e verifica ls-remote.
- DB: MariaDB in Docker (santo-database-santo-1, db mio_database). Backup: scripts/db_backup.sh -> ~/backups-db (fuori da git). Backup PRIMA di toccare dati/schema. Non mostrare mai segreti/hash.
- Backend: container santo-web-automatico-1 su 8083 (se connection reset: docker restart santo-web-automatico-1). Frontend: app-mobile, `npx ng serve --host 0.0.0.0` (4200).
- Install mobile: npm ci --ignore-scripts. Test: composer test. Build: npm run build.
- Account locali di sviluppo: devtest (ADMIN), admin (SUPER), ope (operatore); password nel DB locale / scripts/create_test_user.php, vedi /home/santo/CREDENZIALI_DEV.txt (fuori dal repo).
- Da fare: Stripe test + PayPal sandbox end-to-end, prezzi definitivi, deep link nativo, cambiare password admin/APP_TOKEN_SECRET in produzione, test Playwright (Node 20+).
- Operatori in trial scadono ~2026-10-11.
