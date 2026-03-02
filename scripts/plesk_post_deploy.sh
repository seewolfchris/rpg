#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

if [[ ! -f .env ]]; then
  echo "ERROR: .env fehlt im Projektordner ($PROJECT_ROOT)."
  echo "Bitte zuerst .env in Plesk setzen/erstellen."
  exit 1
fi

echo "[1/6] Composer dependencies installieren (production)..."
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

echo "[2/6] APP_KEY prüfen..."
if ! grep -Eq '^APP_KEY=base64:' .env; then
  php artisan key:generate --force --no-interaction
fi

echo "[3/6] Datenbank migrieren..."
php artisan migrate --force --no-interaction

echo "[4/6] Storage-Link sicherstellen..."
php artisan storage:link --no-interaction || true

echo "[5/6] Caches bereinigen..."
php artisan optimize:clear --no-interaction

echo "[6/6] Performance-Caches aufbauen..."
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction

echo "Deploy abgeschlossen."
