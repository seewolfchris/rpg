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

is_falsy_env() {
  local value
  value="$(printf '%s' "${1:-}" | tr '[:upper:]' '[:lower:]' | xargs)"

  [[ "$value" == "0" || "$value" == "false" || "$value" == "no" || "$value" == "off" ]]
}

is_empty_or_falsy_env() {
  local value
  value="$(printf '%s' "${1:-}" | xargs)"

  if [[ -z "$value" ]]; then
    return 0
  fi

  is_falsy_env "$value"
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

echo "[6/10] Queue- und Security-Preflight prüfen..."
queue_connection="$(read_env_var_from_dotenv "QUEUE_CONNECTION")"
normalized_queue_connection="$(printf '%s' "${queue_connection:-}" | tr '[:upper:]' '[:lower:]' | xargs)"
if [[ -z "$normalized_queue_connection" ]]; then
  echo "ERROR: QUEUE_CONNECTION fehlt in .env."
  echo "Setze QUEUE_CONNECTION=redis und richte einen Queue-Worker ein."
  exit 1
fi
if [[ "$normalized_queue_connection" != "redis" ]]; then
  echo "ERROR: QUEUE_CONNECTION=$queue_connection ist fuer Produktion nicht zulaessig."
  echo "Setze QUEUE_CONNECTION=redis und betreibe queue:work."
  exit 1
fi

cache_store="$(read_env_var_from_dotenv "CACHE_STORE")"
normalized_cache_store="$(printf '%s' "${cache_store:-}" | tr '[:upper:]' '[:lower:]' | xargs)"
if [[ -z "$normalized_cache_store" ]]; then
  echo "ERROR: CACHE_STORE fehlt in .env."
  echo "Setze CACHE_STORE=redis."
  exit 1
fi
if [[ "$normalized_cache_store" != "redis" ]]; then
  echo "ERROR: CACHE_STORE=$cache_store ist fuer Produktion nicht zulaessig."
  echo "Setze CACHE_STORE=redis."
  exit 1
fi

queue_after_commit="$(read_env_var_from_dotenv "QUEUE_AFTER_COMMIT")"
if is_empty_or_falsy_env "$queue_after_commit"; then
  echo "ERROR: QUEUE_AFTER_COMMIT fehlt, ist leer oder deaktiviert."
  echo "Setze QUEUE_AFTER_COMMIT=true (commit-sicheres Queue-Dispatching)."
  exit 1
fi

app_env="$(read_env_var_from_dotenv "APP_ENV")"
normalized_app_env="$(printf '%s' "${app_env:-}" | tr '[:upper:]' '[:lower:]' | xargs)"
if [[ "$normalized_app_env" == "production" || "$normalized_app_env" == "prod" ]]; then
  session_secure_cookie="$(read_env_var_from_dotenv "SESSION_SECURE_COOKIE")"
  if is_empty_or_falsy_env "$session_secure_cookie"; then
    echo "ERROR: SESSION_SECURE_COOKIE fehlt, ist leer oder in Produktion deaktiviert."
    echo "Setze SESSION_SECURE_COOKIE=true (oder entferne den Key fuer sicheren Fallback)."
    exit 1
  fi

  trusted_proxies="$(read_env_var_from_dotenv "TRUSTED_PROXIES")"
  if [[ -z "$trusted_proxies" ]]; then
    echo "ERROR: TRUSTED_PROXIES fehlt in Produktion."
    echo "Setze TRUSTED_PROXIES auf Proxy-IP(s)/CIDR (oder '*' nur bei voll vertrauenswuerdiger Proxy-Kette)."
    exit 1
  fi

  hsts_max_age="$(read_env_var_from_dotenv "SECURITY_HSTS_MAX_AGE")"
  if [[ -n "$hsts_max_age" && "$hsts_max_age" =~ ^[0-9]+$ && "$hsts_max_age" -le 0 ]]; then
    echo "ERROR: SECURITY_HSTS_MAX_AGE ist in Produktion <= 0."
    echo "Setze SECURITY_HSTS_MAX_AGE auf einen positiven Wert (z. B. 31536000)."
    exit 1
  fi
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
