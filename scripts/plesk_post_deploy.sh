#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"
echo "=== PLESK POST-DEPLOY START $(date '+%Y-%m-%d %H:%M:%S') ==="
trap 'echo "=== PLESK POST-DEPLOY FAILED WITH CODE $? ==="' ERR

if [[ ! -f .env ]]; then
  echo "ERROR: .env fehlt im Projektordner ($PROJECT_ROOT)."
  echo "Bitte zuerst .env in Plesk setzen/erstellen."
  exit 1
fi

read_env_var_from_dotenv() {
  local key="$1"
  local value

  value="$(awk -F '=' -v key="$key" '$1 == key { sub(/^[^=]*=/, "", $0); print $0; exit }' .env)"
  value="${value%$'\r'}"
  value="${value#\"}"
  value="${value%\"}"
  value="${value#\'}"
  value="${value%\'}"

  printf '%s\n' "$value"
}

# Use a Plesk PHP binary >= 8.5 so composer/artisan match the project lockfile.
PHP_BIN="${PHP_BIN:-}"
if [[ -z "$PHP_BIN" ]]; then
  for candidate in /opt/plesk/php/8.5/bin/php; do
    if [[ -x "$candidate" ]]; then
      PHP_BIN="$candidate"
      break
    fi
  done
fi
PHP_BIN="${PHP_BIN:-php}"

if ! "$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.5.0", ">=") ? 0 : 1);'; then
  echo "ERROR: Falsche PHP-CLI Version ($("$PHP_BIN" -r 'echo PHP_VERSION;')). Benötigt >= 8.5."
  echo "Setze in Plesk/CLI PHP 8.5+ oder exportiere PHP_BIN=/opt/plesk/php/8.5/bin/php."
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

echo "[1/10] Composer dependencies installieren (production)..."
if [[ "$COMPOSER_PATH" == *.phar ]] || [[ "$COMPOSER_PATH" == "$COMPOSER_PHAR_DEFAULT" ]]; then
  "$PHP_BIN" "$COMPOSER_PATH" install --no-dev --prefer-dist --no-interaction --optimize-autoloader
else
  "$COMPOSER_PATH" install --no-dev --prefer-dist --no-interaction --optimize-autoloader
fi

echo "[2/10] Smoke-Test: perf:posts-latest-by-id-benchmark Command..."
if "$PHP_BIN" artisan list --no-ansi | grep -q "perf:posts-latest-by-id-benchmark"; then
  if "$PHP_BIN" artisan perf:posts-latest-by-id-benchmark --help > /dev/null 2>&1; then
    echo "[deploy] ✅ perf:posts-latest-by-id-benchmark Command ist registriert und ausführbar"
  else
    echo "[deploy] ❌ CRITICAL: Command existiert in 'list', kann aber --help nicht ausführen!"
    exit 1
  fi
else
  echo "[deploy] ❌ CRITICAL: perf:posts-latest-by-id-benchmark Command fehlt komplett nach Deploy!"
  echo "[deploy]     → Command-Klasse / Autoloader / ServiceProvider nicht geladen?"
  exit 1
fi

echo "[3/10] Dev-Hotfile entfernen (falls vorhanden)..."
rm -f public/hot || true

echo "[4/10] Frontend-Build prüfen..."
if [[ ! -f public/build/manifest.json ]]; then
  echo "ERROR: Frontend-Build fehlt (public/build/manifest.json)."
  echo "Bitte vor dem Deploy lokal 'npm run build' ausfuehren und Build-Dateien committen."
  exit 1
fi

echo "[5/10] APP_KEY prüfen..."
app_key="$(read_env_var_from_dotenv "APP_KEY")"
if [[ -z "$app_key" || "$app_key" != base64:* ]]; then
  echo "ERROR: APP_KEY fehlt oder ist ungueltig."
  echo "Setze APP_KEY in Plesk (.env), aber rotiere ihn nicht automatisch im Deploy."
  exit 1
fi

echo "[6/10] Queue-Preflight prüfen..."
queue_connection="$(read_env_var_from_dotenv "QUEUE_CONNECTION")"
if [[ -z "$queue_connection" ]]; then
  echo "ERROR: QUEUE_CONNECTION fehlt in .env."
  echo "Setze QUEUE_CONNECTION=database und richte einen Queue-Worker ein."
  exit 1
fi
if [[ "$queue_connection" == "sync" ]]; then
  echo "ERROR: QUEUE_CONNECTION=sync ist fuer Produktion nicht zulaessig."
  echo "Setze QUEUE_CONNECTION=database und betreibe queue:work."
  exit 1
fi

echo "[7/10] Datenbank migrieren..."
"$PHP_BIN" artisan migrate --force --no-interaction

echo "[8/10] Storage-Link sicherstellen..."
"$PHP_BIN" artisan storage:link --no-interaction || true

echo "[9/10] Caches bereinigen..."
"$PHP_BIN" artisan optimize:clear --no-interaction

echo "[10/10] Performance-Caches aufbauen..."
"$PHP_BIN" artisan config:cache --no-interaction
"$PHP_BIN" artisan route:cache --no-interaction
"$PHP_BIN" artisan view:cache --no-interaction

echo "Deploy abgeschlossen."
