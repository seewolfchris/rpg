#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

PHP_BIN="${PHP_BIN:-php}"
BASE_URL="${SMOKE_BASE_URL:-http://127.0.0.1:8000}"
WORLD_SLUG="${SMOKE_WORLD_SLUG:-${WORLD_DEFAULT_SLUG:-}}"
START_SERVER="${SMOKE_START_SERVER:-1}"
SMOKE_MODE="${SMOKE_MODE:-http}"
SMOKE_EFFECTIVE_MODE="$SMOKE_MODE"
ALLOW_ARTISAN_FALLBACK="${SMOKE_ALLOW_ARTISAN_FALLBACK:-0}"
TMP_BODY_FILE="${SMOKE_TMP_BODY_FILE:-storage/app/smoke/release_smoke_body.txt}"
TMP_HEADER_FILE="${SMOKE_TMP_HEADER_FILE:-storage/app/smoke/release_smoke_header.txt}"
REPORT_OUT="${SMOKE_REPORT_OUT:-}"
SMOKE_STARTED_AT="$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
declare -a SMOKE_RESULTS=()
SERVER_PID=""
HTTP_CHECKS_EXECUTED="no"
FALLBACK_USED="no"
FALLBACK_ENABLED="no"

if [[ "$ALLOW_ARTISAN_FALLBACK" == "1" ]]; then
  FALLBACK_ENABLED="yes"
fi

cleanup() {
  if [[ -n "$SERVER_PID" ]]; then
    kill "$SERVER_PID" >/dev/null 2>&1 || true
    wait "$SERVER_PID" >/dev/null 2>&1 || true
  fi
}
trap cleanup EXIT

require_cmd() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "ERROR: required command not found: $1"
    exit 1
  fi
}

require_non_empty() {
  local value="$1"
  local label="$2"
  local hint="$3"

  if [[ -z "$value" ]]; then
    echo "ERROR: $label is empty. $hint"
    exit 1
  fi
}

read_env_var_from_dotenv() {
  local key="$1"
  local env_file="${SMOKE_ENV_FILE:-.env}"

  if [[ ! -f "$env_file" ]]; then
    return
  fi

  local value
  value="$(awk -F '=' -v key="$key" '$1 == key { sub(/^[^=]*=/, "", $0); print $0; exit }' "$env_file")"
  value="${value%$'\r'}"
  value="${value#\"}"
  value="${value%\"}"
  value="${value#\'}"
  value="${value%\'}"

  printf '%s\n' "$value"
}

ensure_parent_dir() {
  local path="$1"
  mkdir -p "$(dirname "$path")"
}

is_local_base_url() {
  case "$BASE_URL" in
    http://127.0.0.1*|http://localhost*|https://127.0.0.1*|https://localhost*)
      return 0
      ;;
    *)
      return 1
      ;;
  esac
}

record_result() {
  local message="$1"
  SMOKE_RESULTS+=("$message")
}

write_report() {
  if [[ -z "$REPORT_OUT" ]]; then
    return
  fi

  local report_dir
  report_dir="$(dirname "$REPORT_OUT")"
  mkdir -p "$report_dir"

  {
    echo "# Release Smoke Report"
    echo
    echo "- Generated at: \`$SMOKE_STARTED_AT\`"
    echo "- Requested mode: \`$SMOKE_MODE\`"
    echo "- Effective mode: \`$SMOKE_EFFECTIVE_MODE\`"
    echo "- HTTP checks executed: \`$HTTP_CHECKS_EXECUTED\`"
    echo "- Fallback enabled: \`$FALLBACK_ENABLED\`"
    echo "- Fallback used: \`$FALLBACK_USED\`"
    echo "- Base URL: \`$BASE_URL\`"
    echo "- World slug: \`$WORLD_SLUG\`"
    echo
    echo "## Checks"
    if [[ ${#SMOKE_RESULTS[@]} -eq 0 ]]; then
      echo "- (no checks recorded)"
    else
      for result in "${SMOKE_RESULTS[@]}"; do
        echo "- $result"
      done
    fi
  } >"$REPORT_OUT"

  echo "Saved smoke report: $REPORT_OUT"
}

check_status() {
  local path="$1"
  local expected="$2"
  local url="${BASE_URL}${path}"
  local code

  code="$(curl -sS -o "$TMP_BODY_FILE" -w '%{http_code}' "$url")"

  if [[ "$code" != "$expected" ]]; then
    echo "ERROR: $url returned HTTP $code (expected $expected)"
    echo "Body preview:"
    head -n 20 "$TMP_BODY_FILE" || true
    exit 1
  fi

  record_result "\`GET $path\` -> \`$code\`"
  echo "OK   $path -> $code"
}

check_redirect() {
  local path="$1"
  local expected_status="$2"
  local expected_location_fragment="$3"
  local url="${BASE_URL}${path}"
  local code
  local location

  code="$(curl -sS -o "$TMP_BODY_FILE" -D "$TMP_HEADER_FILE" -w '%{http_code}' "$url")"
  location="$(awk -F': ' 'tolower($1) == "location" {gsub(/\r/, "", $2); print $2}' "$TMP_HEADER_FILE" | tail -n 1)"

  if [[ "$code" != "$expected_status" ]]; then
    echo "ERROR: $url returned HTTP $code (expected redirect status $expected_status)"
    exit 1
  fi

  if [[ -z "$location" || "$location" != *"$expected_location_fragment"* ]]; then
    echo "ERROR: $url redirect location '$location' does not contain '$expected_location_fragment'"
    exit 1
  fi

  record_result "\`GET $path\` -> \`$code\` (\`$location\`)"
  echo "OK   $path -> $code (${location})"
}

check_header_contains() {
  local path="$1"
  local header_name="$2"
  local expected_fragment="$3"
  local url="${BASE_URL}${path}"

  if ! curl -sSI "$url" | grep -i "^${header_name}:" | grep -qi "$expected_fragment"; then
    echo "ERROR: ${path} missing expected ${header_name} fragment: ${expected_fragment}"
    exit 1
  fi

  record_result "\`HEAD $path\` has \`$header_name\` (contains \`$expected_fragment\`)"
  echo "OK   header ${header_name} on ${path}"
}

run_artisan_fallback() {
  echo "Running artisan fallback smoke checks..."

  "$PHP_BIN" artisan route:list --path=up >/dev/null
  "$PHP_BIN" artisan route:list --path=welten >/dev/null
  "$PHP_BIN" artisan route:list --path=wissen >/dev/null
  "$PHP_BIN" artisan route:list --path='w/{world:slug}' >/dev/null
  "$PHP_BIN" artisan route:list --path=login >/dev/null
  "$PHP_BIN" artisan route:list --path=hilfe >/dev/null
  "$PHP_BIN" artisan about --only=environment >/dev/null

  record_result "\`artisan route:list\` checks for smoke-critical paths passed"
  record_result "\`artisan about --only=environment\` passed"
  echo "OK   artisan fallback checks passed."
}

require_cmd curl
require_cmd "$PHP_BIN"

if [[ -z "$WORLD_SLUG" ]]; then
  WORLD_SLUG="$(read_env_var_from_dotenv "WORLD_DEFAULT_SLUG")"
fi

if [[ "$SMOKE_MODE" == "http" && "$START_SERVER" == "1" ]] && ! is_local_base_url; then
  echo "Skipping local Laravel server start for remote BASE_URL: $BASE_URL"
  START_SERVER="0"
fi

ensure_parent_dir "$TMP_BODY_FILE"
ensure_parent_dir "$TMP_HEADER_FILE"

if [[ "$SMOKE_MODE" == "artisan" ]]; then
  SMOKE_EFFECTIVE_MODE="artisan"
  run_artisan_fallback
  write_report
  echo "Smoke checks passed (artisan mode)."
  exit 0
fi

if [[ "$SMOKE_MODE" != "http" ]]; then
  echo "ERROR: unsupported SMOKE_MODE='$SMOKE_MODE' (expected 'http' or 'artisan')"
  exit 1
fi

require_non_empty "$WORLD_SLUG" "SMOKE_WORLD_SLUG/WORLD_DEFAULT_SLUG" "Set SMOKE_WORLD_SLUG or WORLD_DEFAULT_SLUG (e.g. 'my-world')."

if [[ "$START_SERVER" == "1" ]]; then
  echo "Starting local Laravel server for smoke checks..."
  "$PHP_BIN" artisan serve --host=127.0.0.1 --port=8000 >/tmp/release_smoke_server.log 2>&1 &
  SERVER_PID="$!"

  for _ in {1..30}; do
    if curl -sS "$BASE_URL/up" >/dev/null 2>&1; then
      break
    fi
    sleep 1
  done
fi

if ! curl -sS "$BASE_URL/up" >/dev/null 2>&1; then
  if [[ "$ALLOW_ARTISAN_FALLBACK" == "1" ]]; then
    echo "WARN: HTTP smoke endpoint $BASE_URL/up is not reachable; running explicit artisan fallback (SMOKE_ALLOW_ARTISAN_FALLBACK=1)."
    record_result "HTTP endpoint \`$BASE_URL/up\` unreachable; switched to explicit artisan fallback"
    SMOKE_EFFECTIVE_MODE="artisan-fallback"
    FALLBACK_USED="yes"
    run_artisan_fallback
    write_report
    echo "Smoke checks passed (artisan fallback mode)."
    exit 0
  fi

  echo "ERROR: HTTP smoke endpoint not reachable at $BASE_URL/up (SMOKE_MODE=http)."
  echo "ERROR: No fallback executed because SMOKE_ALLOW_ARTISAN_FALLBACK=0."
  echo "Hint: Use SMOKE_MODE=artisan for explicit artisan-only checks, or set SMOKE_ALLOW_ARTISAN_FALLBACK=1 to allow fallback."
  record_result "HTTP endpoint \`$BASE_URL/up\` unreachable; HTTP smoke failed"
  write_report
  exit 1
fi

HTTP_CHECKS_EXECUTED="yes"
echo "Running release smoke checks against $BASE_URL (world: $WORLD_SLUG)"

check_status "/up" "200"
check_status "/" "200"
check_status "/welten" "200"
check_status "/w/${WORLD_SLUG}" "200"
check_status "/w/${WORLD_SLUG}/wissen" "200"
check_status "/w/${WORLD_SLUG}/wissen/enzyklopaedie" "200"
check_status "/login" "200"
check_status "/wissen" "200"
check_status "/wissen/enzyklopaedie" "200"
check_redirect "/hilfe" "302" "/wissen"

check_header_contains "/" "X-Request-Id" ""
check_header_contains "/" "X-Robots-Tag" "noindex"

write_report
echo "Smoke checks passed."
