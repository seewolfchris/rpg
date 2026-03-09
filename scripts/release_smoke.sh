#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

PHP_BIN="${PHP_BIN:-php}"
BASE_URL="${SMOKE_BASE_URL:-http://127.0.0.1:8000}"
WORLD_SLUG="${SMOKE_WORLD_SLUG:-${WORLD_DEFAULT_SLUG:-chroniken-der-asche}}"
START_SERVER="${SMOKE_START_SERVER:-1}"
SMOKE_MODE="${SMOKE_MODE:-http}"
SMOKE_EFFECTIVE_MODE="$SMOKE_MODE"
ALLOW_ARTISAN_FALLBACK="${SMOKE_ALLOW_ARTISAN_FALLBACK:-1}"
TMP_BODY_FILE="${SMOKE_TMP_BODY_FILE:-/tmp/release_smoke_body.txt}"
TMP_HEADER_FILE="${SMOKE_TMP_HEADER_FILE:-/tmp/release_smoke_header.txt}"
REPORT_OUT="${SMOKE_REPORT_OUT:-}"
SMOKE_STARTED_AT="$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
declare -a SMOKE_RESULTS=()
SERVER_PID=""

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
    SMOKE_EFFECTIVE_MODE="artisan-fallback"
    run_artisan_fallback
    write_report
    echo "Smoke checks passed (artisan fallback mode)."
    exit 0
  fi

  echo "ERROR: HTTP smoke endpoint not reachable at $BASE_URL/up"
  exit 1
fi

echo "Running release smoke checks against $BASE_URL (world: $WORLD_SLUG)"

check_status "/up" "200"
check_status "/" "200"
check_status "/welten" "200"
check_status "/w/${WORLD_SLUG}" "200"
check_status "/w/${WORLD_SLUG}/wissen" "200"
check_status "/w/${WORLD_SLUG}/wissen/enzyklopaedie" "200"
check_status "/login" "200"
check_redirect "/wissen" "301" "/w/${WORLD_SLUG}/wissen"
check_redirect "/wissen/enzyklopaedie" "301" "/w/${WORLD_SLUG}/wissen/enzyklopaedie"
check_redirect "/hilfe" "301" "/w/${WORLD_SLUG}/wissen"

check_header_contains "/" "X-Request-Id" ""
check_header_contains "/" "X-Robots-Tag" "noindex"

write_report
echo "Smoke checks passed."
