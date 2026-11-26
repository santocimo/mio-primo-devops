#!/usr/bin/env bash
set -euo pipefail

# Backups contain personal data and are stored outside the repository by default.
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEST="${BACKUP_DIR:-$HOME/backups-db}"
DB_NAME="${DB_NAME:-mio_database}"
DB_CONTAINER="${DB_CONTAINER:-mio-primo-devops-database-santo-1}"
DB_COMPOSE_FILE="${DB_COMPOSE_FILE:-$ROOT_DIR/docker-compose.yml}"
DB_COMPOSE_PROJECT="${DB_COMPOSE_PROJECT:-mio-primo-devops}"
DB_SERVICE="${DB_SERVICE:-database-santo}"

umask 077
mkdir -p "$DEST"
chmod 700 "$DEST"
FILE="$DEST/${DB_NAME}_$(date +'%Y%m%d_%H%M%S').sql"

DUMP_COMMAND='
set -eu
secret_file="$(mktemp)"
trap '\''rm -f "$secret_file"'\'' EXIT
chmod 600 "$secret_file"
if [ -r /run/secrets/db_root_password ]; then
    db_password="$(cat /run/secrets/db_root_password)"
elif [ -n "${MYSQL_ROOT_PASSWORD:-}" ]; then
    db_password="$MYSQL_ROOT_PASSWORD"
else
    printf "Database password is unavailable in the container.\n" >&2
    exit 1
fi
printf "[client]\nuser=root\npassword=%s\n" "$db_password" > "$secret_file"
unset db_password
mariadb-dump --defaults-extra-file="$secret_file" --single-transaction "$BACKUP_DB_NAME"
'

if [[ -n "$DB_CONTAINER" ]]; then
    if ! docker exec -e "BACKUP_DB_NAME=$DB_NAME" "$DB_CONTAINER" sh -c "$DUMP_COMMAND" > "$FILE"; then
        rm -f "$FILE"
        printf 'Database backup failed; partial output removed.\n' >&2
        exit 1
    fi
else
    if ! docker compose \
        --project-name "$DB_COMPOSE_PROJECT" \
        --file "$DB_COMPOSE_FILE" \
        exec -T -e "BACKUP_DB_NAME=$DB_NAME" "$DB_SERVICE" sh -c "$DUMP_COMMAND" > "$FILE"; then
        rm -f "$FILE"
        printf 'Database backup failed; partial output removed.\n' >&2
        exit 1
    fi
fi

chmod 600 "$FILE"
printf 'Backup created: %s\n' "$FILE"
