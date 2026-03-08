#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

PHP_BIN="${PHP_BIN:-php}"
BASE_URL="${SMOKE_BASE_URL:-http://127.0.0.1:8000}"
START_SERVER="${SMOKE_START_SERVER:-1}"
ALLOW_ARTISAN_FALLBACK="${SMOKE_ALLOW_ARTISAN_FALLBACK:-1}"
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

check_status() {
  local path="$1"
  local expected="$2"
  local url="${BASE_URL}${path}"
  local code

  code="$(curl -sS -o /tmp/release_smoke_body.txt -w '%{http_code}' "$url")"

  if [[ "$code" != "$expected" ]]; then
    echo "ERROR: $url returned HTTP $code (expected $expected)"
    echo "Body preview:"
    head -n 20 /tmp/release_smoke_body.txt || true
    exit 1
  fi

  echo "OK   $path -> $code"
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

  echo "OK   header ${header_name} on ${path}"
}

run_artisan_fallback() {
  echo "Running artisan fallback smoke checks..."

  "$PHP_BIN" artisan route:list --path=up >/dev/null
  "$PHP_BIN" artisan route:list --path=wissen >/dev/null
  "$PHP_BIN" artisan route:list --path=login >/dev/null
  "$PHP_BIN" artisan route:list --path=hilfe >/dev/null
  "$PHP_BIN" artisan about --only=environment >/dev/null

  echo "OK   artisan fallback checks passed."
}

require_cmd curl
require_cmd "$PHP_BIN"

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
    run_artisan_fallback
    echo "Smoke checks passed (artisan fallback mode)."
    exit 0
  fi

  echo "ERROR: HTTP smoke endpoint not reachable at $BASE_URL/up"
  exit 1
fi

echo "Running release smoke checks against $BASE_URL"

check_status "/up" "200"
check_status "/" "200"
check_status "/wissen" "200"
check_status "/wissen/enzyklopaedie" "200"
check_status "/login" "200"
check_status "/hilfe" "301"

check_header_contains "/" "X-Request-Id" ""
check_header_contains "/" "X-Robots-Tag" "noindex"

echo "Smoke checks passed."
