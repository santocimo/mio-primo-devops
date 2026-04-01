#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_DIR="$ROOT_DIR/backups"

MYSQL_USER="root"
MYSQL_PASS="password_segreta"
MYSQL_DB="mio_database"
DOCKER_SERVICE="database-santo"

mkdir -p "$BACKUP_DIR"
TIMESTAMP="$(date +'%Y%m%d_%H%M%S')"
BACKUP_FILE="$BACKUP_DIR/${MYSQL_DB}_$TIMESTAMP.sql"

printf "Backing up %s to %s\n" "$MYSQL_DB" "$BACKUP_FILE"

docker compose exec -T "$DOCKER_SERVICE" /usr/bin/mariadb-dump \
  --user="$MYSQL_USER" \
  --password="$MYSQL_PASS" \
  "$MYSQL_DB" > "$BACKUP_FILE"

printf "Backup complete: %s\n" "$BACKUP_FILE"
printf "Tip: keep multiple copies of backups outside this machine (cloud, external drive, etc.).\n"
