#!/usr/bin/env bash
set -euo pipefail

# Dump del DB del container MariaDB fuori dal repo (default: ~/backups-db). Nessuna password nello script:
# usa MYSQL_ROOT_PASSWORD gia' presente nel container. Il dump contiene dati reali: non committarlo.
# Ripristino: docker exec -i <container> sh -c 'mariadb -uroot -p"$MYSQL_ROOT_PASSWORD"' < file.sql
CONTAINER="${DB_CONTAINER:-santo-database-santo-1}"
DEST="${BACKUP_DIR:-$HOME/backups-db}"
mkdir -p "$DEST"; chmod 700 "$DEST"
FILE="$DEST/mio_database_$(date +%Y%m%d_%H%M%S).sql"
docker exec "$CONTAINER" sh -c 'mariadb-dump -uroot -p"$MYSQL_ROOT_PASSWORD" --all-databases --single-transaction' > "$FILE"
chmod 600 "$FILE"
echo "Backup creato: $FILE"
