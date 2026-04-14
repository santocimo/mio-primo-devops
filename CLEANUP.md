# Files to Archive or Remove

This document lists files that were part of the legacy development and can be safely archived or removed.

## Safe to Delete

These files are completely replaced by the new professional structure:

### Legacy Test/Demo Files
- `segreto.html` - Demo/secret file (no longer needed)
- `contatti.html` - Legacy HTML contact file (use application interface instead)
- `vecchio_index.txt` - Old index file (replaced by professional index.php)
- `chat_history.txt` - Chat logs (not part of application)

### Legacy Scripts
- `save_code.sh` - Old bash script (functionality moved to application)
- `backup_db.sh` - Use docker-compose commands instead: `docker-compose exec database-santo mysqldump ...`
- `deploy.ps1` - Windows deployment script (replaced by Docker)

### Old Database Files
- `backup_progetto.sql` - Old backup file (keep in git history, archive locally if needed)
- `comuni.csv` - Legacy data import (archived)
- `comuni.json` - JSON version of legacy data

### Legacy Testing
- `phpunit.xml` - Partially replaced (review before deleting to preserve any custom phpunit config)
- `tests/ExampleTest.php` - Example test (review and update if needed)

## Safe to Archive (Keep but Move Out of Main Dir)

These files have historical value and can be archived:

```bash
# Create archive directory
mkdir -p backups/legacy

# Move these to backups/legacy/:
# - segreto.html
# - contatti.html  
# - vecchio_index.txt
# - backup_db.sh
# - save_code.sh
# - deploy.ps1
# - chat_history.txt
# - comuni.csv
# - comuni.json
# - backup_progetto.sql (keep most recent backup elsewhere)
```

## Keep and Update

These files are essential and should be updated/maintained:

- `index.php` - Update to new architecture
- `login.php` - Update to use AuthManager
- `appointments.php`, `contacts.php`, `users.php`, etc. - Update all application files
- `inc/labels.php` - Keep updated with application
- `inc/validation.php` - Keep updated with application
- `Dockerfile` - Professional container definition, keep current
- `docker-compose.yml` - Infrastructure configuration, keep current
- `migrations/*.sql` - Database schema, keep and version control

## Files to Review Before Cleanup

### Configuration Files
- `.docker/` - Review if contains necessary configuration
- `importa_completo.php` - Check if data import needed, else can delete
- `cerca_comuni.php` - Check if city search needed, else can delete

### Documentation
- `manuale.md` - Legacy manual (migrate content to INSTALL.md if needed, then delete)

## Cleanup Procedure

1. **Review each file** before deleting:
   ```bash
   cat filename
   ```

2. **Test application works** after removing each file:
   ```bash
   docker-compose up -d
   # Access http://localhost:8081 and test functionality
   ```

3. **Commit changes** to git:
   ```bash
   git add -A
   git commit -m "Remove legacy files, keep only professional application"
   ```

## After Cleanup

Your project root should contain only:

```
├── bootstrap.php
├── config/
├── src/
├── logs/
├── migrations/
├── tests/
├── vendor/
├── .env (local only, not in git)
├── .env.example
├── .github/
├── .gitignore
├── CHANGELOG.md
├── CONTRIBUTING.md
├── INSTALL.md
├── MIGRATION.md
├── README.md
├── SECURITY.md
├── composer.json
├── composer.lock
├── Dockerfile
├── docker-compose.yml
├── phpunit.xml
├── index.php (updated)
├── login.php (updated)
├── appointments.php (updated)
├── [other professional app files]
└── inc/
    ├── labels.php
    ├── validation.php
    └── security.php (updated - backward compat layer)
```

## Archiving Example

```bash
# Create backup of legacy files
mkdir -p archives/2026-04-14
tar czf archives/2026-04-14/legacy-files.tar.gz \
    segreto.html \
    contatti.html \
    vecchio_index.txt \
    backup_db.sh \
    save_code.sh \
    chat_history.txt \
    comuni.csv \
    comuni.json

# Remove from production
rm segreto.html contatti.html vecchio_index.txt backup_db.sh save_code.sh chat_history.txt comuni.csv comuni.json

# Commit changes
git add -A
git commit -m "Archive legacy files to save space"
```

## Important Notes

- **Always test** the application after removing files
- **Keep git history** - deleted files are still in git history
- **Archive before delete** - keep offline backups if files might be needed
- **Update configuration** - any references to deleted files should be removed
- **Document decisions** - explain why files were removed in commit messages

---

**Legacy Cleanup Date**: April 14, 2026
