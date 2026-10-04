#!/usr/bin/env bash
set -euo pipefail

# Simple dev setup script
# Usage: ./scripts/setup-dev.sh

DB_HOST=${DB_HOST:-localhost}
DB_PORT=${DB_PORT:-3306}
DB_NAME=${DB_NAME:-app_database}
DB_USER=${DB_USER:-root}
DB_PASSWORD=${DB_PASSWORD:-}

echo "Using DB ${DB_USER}@${DB_HOST}:${DB_PORT}/${DB_NAME}"

export DB_HOST DB_PORT DB_NAME DB_USER DB_PASSWORD

echo "Running PHP bootstrap to ensure schema..."
php -r "require 'bootstrap.php'; echo 'Bootstrap loaded OK\n';"

echo "Note: migrations/*.sql are present but DatabaseManager initializes schema automatically."

if [ -f scripts/seed_comuni.php ]; then
  echo "Seeding comuni into database..."
  php scripts/seed_comuni.php
else
  echo "No seed script found: scripts/seed_comuni.php"
fi

echo "Dev setup completed. To start the PHP server run:"
echo "  php -S localhost:8000 -t ."
