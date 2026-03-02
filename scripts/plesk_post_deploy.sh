#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

if [[ ! -f .env ]]; then
  echo "ERROR: .env fehlt im Projektordner ($PROJECT_ROOT)."
  echo "Bitte zuerst .env in Plesk setzen/erstellen."
  exit 1
fi

# Use a Plesk PHP binary >= 8.4 so composer/artisan match the project lockfile.
PHP_BIN="${PHP_BIN:-}"
if [[ -z "$PHP_BIN" ]]; then
  for candidate in /opt/plesk/php/8.5/bin/php /opt/plesk/php/8.4/bin/php /opt/plesk/php/8.3/bin/php; do
    if [[ -x "$candidate" ]]; then
      PHP_BIN="$candidate"
      break
    fi
  done
fi
PHP_BIN="${PHP_BIN:-php}"

if ! "$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.4.0", ">=") ? 0 : 1);'; then
  echo "ERROR: Falsche PHP-CLI Version ($("$PHP_BIN" -r 'echo PHP_VERSION;')). Benoetigt >= 8.4."
  echo "Setze in Plesk/CLI PHP 8.4+ oder exportiere PHP_BIN=/opt/plesk/php/8.4/bin/php."
  exit 1
fi

COMPOSER_PHAR_DEFAULT="/opt/psa/var/modules/composer/composer.phar"
COMPOSER_PATH="${COMPOSER_PATH:-}"
if [[ -z "$COMPOSER_PATH" ]]; then
  if [[ -f "$COMPOSER_PHAR_DEFAULT" ]]; then
    COMPOSER_PATH="$COMPOSER_PHAR_DEFAULT"
  else
    COMPOSER_PATH="$(command -v composer || true)"
  fi
fi

if [[ -z "$COMPOSER_PATH" ]]; then
  echo "ERROR: composer nicht gefunden."
  exit 1
fi

echo "Using PHP binary: $PHP_BIN ($("$PHP_BIN" -r 'echo PHP_VERSION;'))"
echo "Using Composer: $COMPOSER_PATH"

echo "[1/7] Composer dependencies installieren (production)..."
if [[ "$COMPOSER_PATH" == *.phar ]] || [[ "$COMPOSER_PATH" == "$COMPOSER_PHAR_DEFAULT" ]]; then
  "$PHP_BIN" "$COMPOSER_PATH" install --no-dev --prefer-dist --no-interaction --optimize-autoloader
else
  "$COMPOSER_PATH" install --no-dev --prefer-dist --no-interaction --optimize-autoloader
fi

echo "[2/7] Dev-Hotfile entfernen (falls vorhanden)..."
rm -f public/hot || true

echo "[3/7] APP_KEY prüfen..."
if ! grep -Eq '^APP_KEY=' .env; then
  echo "APP_KEY=" >> .env
fi
if ! grep -Eq '^APP_KEY=base64:' .env; then
  "$PHP_BIN" artisan key:generate --force --no-interaction
fi

echo "[4/7] Datenbank migrieren..."
"$PHP_BIN" artisan migrate --force --no-interaction

echo "[5/7] Storage-Link sicherstellen..."
"$PHP_BIN" artisan storage:link --no-interaction || true

echo "[6/7] Caches bereinigen..."
"$PHP_BIN" artisan optimize:clear --no-interaction

echo "[7/7] Performance-Caches aufbauen..."
"$PHP_BIN" artisan config:cache --no-interaction
"$PHP_BIN" artisan route:cache --no-interaction
"$PHP_BIN" artisan view:cache --no-interaction

echo "Deploy abgeschlossen."
