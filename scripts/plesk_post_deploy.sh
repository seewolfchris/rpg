#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

if [[ ! -f .env ]]; then
  echo "ERROR: .env fehlt im Projektordner ($PROJECT_ROOT)."
  echo "Bitte zuerst .env in Plesk setzen/erstellen."
  exit 1
fi

echo "[1/7] Composer dependencies installieren (production)..."
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

echo "[2/7] Dev-Hotfile entfernen (falls vorhanden)..."
rm -f public/hot || true

echo "[3/7] APP_KEY prüfen..."
if ! grep -Eq '^APP_KEY=base64:' .env; then
  php artisan key:generate --force --no-interaction
fi

echo "[4/7] Datenbank migrieren..."
php artisan migrate --force --no-interaction

echo "[5/7] Storage-Link sicherstellen..."
php artisan storage:link --no-interaction || true

echo "[6/7] Caches bereinigen..."
php artisan optimize:clear --no-interaction

echo "[7/7] Performance-Caches aufbauen..."
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction

echo "Deploy abgeschlossen."
