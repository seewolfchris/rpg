#!/usr/bin/env bash
set -u
set -o pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

WARN_COUNT=0
INFO_COUNT=0
OK_COUNT=0

ENV_LOCAL=".env.example"
ENV_PLESK=".env.plesk.example"

CRITICAL_KEYS=(
  APP_ENV
  APP_DEBUG
  APP_URL
  DB_CONNECTION
  CACHE_STORE
  QUEUE_CONNECTION
  SESSION_DRIVER
  QUEUE_AFTER_COMMIT
  TRUSTED_PROXIES
  FILESYSTEM_DISK
)

OPTIONAL_KEYS=(
  SESSION_SECURE_COOKIE
  REDIS_HOST
  REDIS_PORT
  REDIS_CLIENT
  REDIS_QUEUE_CONNECTION
  WORLD_DEFAULT_SLUG
  WEBPUSH_VAPID_PUBLIC_KEY
  WEBPUSH_VAPID_PRIVATE_KEY
)

log_ok() {
  OK_COUNT=$((OK_COUNT + 1))
  echo "[config-drift][OK] $*"
}

log_warn() {
  WARN_COUNT=$((WARN_COUNT + 1))
  echo "[config-drift][WARN] $*"
}

log_info() {
  INFO_COUNT=$((INFO_COUNT + 1))
  echo "[config-drift][INFO] $*"
}

has_key() {
  local file="$1"
  local key="$2"
  grep -qE "^${key}=" "$file"
}

value_for() {
  local file="$1"
  local key="$2"
  awk -F= -v key="$key" '
    $0 ~ ("^" key "=") {
      sub(/^[^=]*=/, "", $0)
      print $0
      exit
    }
  ' "$file"
}

join_by_comma() {
  local first=1
  local item

  for item in "$@"; do
    if [[ "$first" -eq 1 ]]; then
      printf "%s" "$item"
      first=0
    else
      printf ", %s" "$item"
    fi
  done
}

check_file_exists() {
  local file="$1"
  if [[ -f "$file" ]]; then
    log_ok "Datei vorhanden: $file"
    return 0
  fi

  log_warn "Datei fehlt: $file"
  return 1
}

check_required_keys() {
  local file="$1"
  local -a missing=()
  local key

  for key in "${CRITICAL_KEYS[@]}"; do
    if ! has_key "$file" "$key"; then
      missing+=("$key")
    fi
  done

  if [[ "${#missing[@]}" -eq 0 ]]; then
    log_ok "$file: alle kritischen Schluessel vorhanden"
  else
    log_warn "$file: fehlende kritische Schluessel: $(join_by_comma "${missing[@]}")"
  fi
}

check_optional_keys() {
  local file="$1"
  local -a present=()
  local -a missing=()
  local key

  for key in "${OPTIONAL_KEYS[@]}"; do
    if has_key "$file" "$key"; then
      present+=("$key")
    else
      missing+=("$key")
    fi
  done

  if [[ "${#present[@]}" -gt 0 ]]; then
    log_info "$file: optionale Schluessel vorhanden: $(join_by_comma "${present[@]}")"
  else
    log_info "$file: keine der abgefragten optionalen Schluessel vorhanden"
  fi

  if [[ "${#missing[@]}" -gt 0 ]]; then
    log_info "$file: optionale Schluessel fehlen (nicht blockierend): $(join_by_comma "${missing[@]}")"
  fi

  if has_key "$file" "VAPID_PUBLIC_KEY" || has_key "$file" "VAPID_PRIVATE_KEY"; then
    log_info "$file: Projekt nutzt VAPID_PUBLIC_KEY/VAPID_PRIVATE_KEY (anstelle von WEBPUSH_VAPID_*)."
  fi
}

check_local_profile() {
  local file="$1"
  local value=""

  value="$(value_for "$file" "APP_ENV")"
  if [[ -z "$value" ]]; then
    log_warn "$file: APP_ENV fehlt"
  elif [[ "$value" == "local" ]]; then
    log_ok "$file: APP_ENV=local passt zum Local-Profil"
  else
    log_info "$file: APP_ENV=$value (lokal zulaessig, pruefen ob bewusst)"
  fi

  value="$(value_for "$file" "APP_DEBUG")"
  if [[ -z "$value" ]]; then
    log_warn "$file: APP_DEBUG fehlt"
  elif [[ "$value" == "true" ]]; then
    log_ok "$file: APP_DEBUG=true ist lokal akzeptabel"
  else
    log_info "$file: APP_DEBUG=$value (lokal zulaessig, pruefen ob bewusst)"
  fi

  value="$(value_for "$file" "QUEUE_CONNECTION")"
  if [[ -z "$value" ]]; then
    log_warn "$file: QUEUE_CONNECTION fehlt"
  elif [[ "$value" == "sync" || "$value" == "database" || "$value" == "redis" ]]; then
    log_ok "$file: QUEUE_CONNECTION=$value ist fuer lokal erlaubt"
  else
    log_warn "$file: QUEUE_CONNECTION=$value ist ungewoehnlich (erwartet: sync|database|redis)"
  fi

  value="$(value_for "$file" "DB_CONNECTION")"
  if [[ -z "$value" ]]; then
    log_warn "$file: DB_CONNECTION fehlt"
  elif [[ "$value" == "sqlite" || "$value" == "mysql" ]]; then
    log_ok "$file: DB_CONNECTION=$value ist fuer lokal erlaubt"
  else
    log_warn "$file: DB_CONNECTION=$value ist ungewoehnlich (erwartet: sqlite|mysql)"
  fi
}

check_plesk_profile() {
  local file="$1"
  local value=""

  value="$(value_for "$file" "APP_ENV")"
  if [[ "$value" == "production" ]]; then
    log_ok "$file: APP_ENV=production"
  else
    log_warn "$file: APP_ENV=$value (erwartet production laut Ops/Release-Leitplanken)"
  fi

  value="$(value_for "$file" "APP_DEBUG")"
  if [[ "$value" == "false" ]]; then
    log_ok "$file: APP_DEBUG=false"
  else
    log_warn "$file: APP_DEBUG=$value (erwartet false fuer Production-Profil)"
  fi

  value="$(value_for "$file" "QUEUE_CONNECTION")"
  if [[ "$value" == "redis" ]]; then
    log_ok "$file: QUEUE_CONNECTION=redis"
  else
    log_warn "$file: QUEUE_CONNECTION=$value (erwartet redis laut Ops/Release-Leitplanken)"
  fi

  value="$(value_for "$file" "CACHE_STORE")"
  if [[ "$value" == "redis" ]]; then
    log_ok "$file: CACHE_STORE=redis"
  else
    log_warn "$file: CACHE_STORE=$value (erwartet redis laut Ops/Release-Leitplanken)"
  fi

  value="$(value_for "$file" "QUEUE_AFTER_COMMIT")"
  if [[ "$value" == "true" ]]; then
    log_ok "$file: QUEUE_AFTER_COMMIT=true"
  else
    log_warn "$file: QUEUE_AFTER_COMMIT=$value (erwartet true laut Ops/Release-Leitplanken)"
  fi

  if has_key "$file" "TRUSTED_PROXIES"; then
    value="$(value_for "$file" "TRUSTED_PROXIES")"
    if [[ -n "$value" ]]; then
      log_ok "$file: TRUSTED_PROXIES ist gesetzt"
    else
      log_warn "$file: TRUSTED_PROXIES ist leer (Proxy-Vertrauen explizit setzen)"
    fi
  else
    log_info "$file: TRUSTED_PROXIES fehlt (bereits in der Schluesselpruefung gemeldet; laut Ops-Doku sollte der Wert gesetzt sein)"
  fi

  if has_key "$file" "FILESYSTEM_DISK"; then
    value="$(value_for "$file" "FILESYSTEM_DISK")"
    if [[ -n "$value" ]]; then
      log_ok "$file: FILESYSTEM_DISK=$value ist bewusst gesetzt"
    else
      log_warn "$file: FILESYSTEM_DISK ist leer"
    fi
  else
    log_warn "$file: FILESYSTEM_DISK fehlt"
  fi

  if has_key "$file" "SESSION_DRIVER"; then
    value="$(value_for "$file" "SESSION_DRIVER")"
    if [[ -z "$value" ]]; then
      log_warn "$file: SESSION_DRIVER ist leer"
    elif [[ "$value" == "file" ]]; then
      log_warn "$file: SESSION_DRIVER=file (ungewoehnlich fuer dokumentierten Production-Standard)"
    else
      log_ok "$file: SESSION_DRIVER=$value ist gesetzt"
    fi
  else
    log_warn "$file: SESSION_DRIVER fehlt"
  fi
}

echo "[config-drift][INFO] Starte Config-Drift-Check (warn-only/report-only)"
echo "[config-drift][INFO] Scope: .env.example vs .env.plesk.example + dokumentierte Ops/Release-Leitplanken"

if check_file_exists "$ENV_LOCAL"; then
  check_required_keys "$ENV_LOCAL"
  check_optional_keys "$ENV_LOCAL"
  check_local_profile "$ENV_LOCAL"
fi

if check_file_exists "$ENV_PLESK"; then
  check_required_keys "$ENV_PLESK"
  check_optional_keys "$ENV_PLESK"
  check_plesk_profile "$ENV_PLESK"
fi

echo "[config-drift][INFO] Zusammenfassung: WARN=${WARN_COUNT}, OK=${OK_COUNT}, INFO=${INFO_COUNT}"
echo "[config-drift][INFO] PR-03 ist absichtlich report-only/warn-only; der Check ist nicht blockierend."
echo "[config-drift][INFO] Spaetere PRs koennen einzelne Regeln gezielt haerten."

exit 0
