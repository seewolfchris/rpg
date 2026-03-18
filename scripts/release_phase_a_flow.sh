#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

usage() {
  cat <<'USAGE'
Usage:
  scripts/release_phase_a_flow.sh [options]

Description:
  Executes the operational Phase-A rollout flow in fixed order:
    1) migrate --force
    2) optimize:clear + config:cache
    3) release_phase_a_smoke.sh as hard gate

Options:
  --base-url <url>            Base URL for phase-a smoke (default: http://127.0.0.1:8000)
  --world-slug <slug>         World slug for smoke routing (default: WORLD_DEFAULT_SLUG)
  --smoke-mode <http|artisan> Smoke mode for release_phase_a_smoke.sh (default: http)
  --report-out <path>         Optional markdown report path for phase-a smoke
  --strict-flags <0|1>        Enforce wave3/4 phase-a flag baseline (default: 1)
  --run-base-smoke <0|1>      Run base release_smoke.sh before phase-a gates (default: 1)
  --run-test-gates <0|1>      Run local artisan test gates inside phase-a smoke (default: 0)
  --skip-migrate              Skip php artisan migrate --force
  --skip-cache                Skip optimize:clear + config:cache
  --dry-run                   Print commands only, do not execute
  -h, --help                  Show this help

Examples:
  scripts/release_phase_a_flow.sh --base-url "https://rpg.c76.org" --world-slug "<world-slug>" --report-out "docs/SMOKE-PHASE-A.md"
  scripts/release_phase_a_flow.sh --smoke-mode artisan --skip-migrate
USAGE
}

require_cmd() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "ERROR: required command not found: $1"
    exit 1
  fi
}

require_toggle() {
  local value="$1"
  local label="$2"

  if [[ "$value" != "0" && "$value" != "1" ]]; then
    echo "ERROR: $label must be 0 or 1 (received: $value)"
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

run_cmd() {
  local rendered=()
  for part in "$@"; do
    rendered+=("$part")
  done
  echo "+ ${rendered[*]}"
  if [[ "$DRY_RUN" == "1" ]]; then
    return 0
  fi

  "$@"
}

run_phase_a_smoke() {
  local rendered="PHASE_A_BASE_URL=$BASE_URL PHASE_A_WORLD_SLUG=$WORLD_SLUG PHASE_A_SMOKE_MODE=$SMOKE_MODE PHASE_A_STRICT_FLAGS=$STRICT_FLAGS PHASE_A_RUN_BASE_SMOKE=$RUN_BASE_SMOKE PHASE_A_RUN_TEST_GATES=$RUN_TEST_GATES"
  if [[ -n "$REPORT_OUT" ]]; then
    rendered="$rendered PHASE_A_REPORT_OUT=$REPORT_OUT"
  fi
  rendered="$rendered scripts/release_phase_a_smoke.sh"
  echo "+ $rendered"

  if [[ "$DRY_RUN" == "1" ]]; then
    return 0
  fi

  PHASE_A_BASE_URL="$BASE_URL" \
  PHASE_A_WORLD_SLUG="$WORLD_SLUG" \
  PHASE_A_SMOKE_MODE="$SMOKE_MODE" \
  PHASE_A_STRICT_FLAGS="$STRICT_FLAGS" \
  PHASE_A_RUN_BASE_SMOKE="$RUN_BASE_SMOKE" \
  PHASE_A_RUN_TEST_GATES="$RUN_TEST_GATES" \
  PHASE_A_REPORT_OUT="$REPORT_OUT" \
  scripts/release_phase_a_smoke.sh
}

PHP_BIN="${PHP_BIN:-php}"
BASE_URL="${PHASE_A_BASE_URL:-${SMOKE_BASE_URL:-http://127.0.0.1:8000}}"
WORLD_SLUG="${PHASE_A_WORLD_SLUG:-${SMOKE_WORLD_SLUG:-${WORLD_DEFAULT_SLUG:-}}}"
SMOKE_MODE="${PHASE_A_SMOKE_MODE:-${SMOKE_MODE:-http}}"
REPORT_OUT="${PHASE_A_REPORT_OUT:-}"
STRICT_FLAGS="${PHASE_A_STRICT_FLAGS:-1}"
RUN_BASE_SMOKE="${PHASE_A_RUN_BASE_SMOKE:-1}"
RUN_TEST_GATES="${PHASE_A_RUN_TEST_GATES:-0}"
SKIP_MIGRATE="0"
SKIP_CACHE="0"
DRY_RUN="0"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --base-url)
      BASE_URL="${2:-}"
      shift 2
      ;;
    --world-slug)
      WORLD_SLUG="${2:-}"
      shift 2
      ;;
    --smoke-mode)
      SMOKE_MODE="${2:-}"
      shift 2
      ;;
    --report-out)
      REPORT_OUT="${2:-}"
      shift 2
      ;;
    --strict-flags)
      STRICT_FLAGS="${2:-}"
      shift 2
      ;;
    --run-base-smoke)
      RUN_BASE_SMOKE="${2:-}"
      shift 2
      ;;
    --run-test-gates)
      RUN_TEST_GATES="${2:-}"
      shift 2
      ;;
    --skip-migrate)
      SKIP_MIGRATE="1"
      shift
      ;;
    --skip-cache)
      SKIP_CACHE="1"
      shift
      ;;
    --dry-run)
      DRY_RUN="1"
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "ERROR: unknown argument '$1'"
      usage
      exit 1
      ;;
  esac
done

if [[ "$SMOKE_MODE" != "http" && "$SMOKE_MODE" != "artisan" ]]; then
  echo "ERROR: --smoke-mode must be 'http' or 'artisan' (received: $SMOKE_MODE)"
  exit 1
fi

require_toggle "$STRICT_FLAGS" "--strict-flags"
require_toggle "$RUN_BASE_SMOKE" "--run-base-smoke"
require_toggle "$RUN_TEST_GATES" "--run-test-gates"

if [[ "$SMOKE_MODE" == "http" ]]; then
  require_non_empty "$WORLD_SLUG" "--world-slug/PHASE_A_WORLD_SLUG/WORLD_DEFAULT_SLUG" "Set --world-slug or WORLD_DEFAULT_SLUG (e.g. 'my-world')."
fi

require_cmd "$PHP_BIN"

echo "[Phase A] Starting rollout flow"
echo "- Base URL: $BASE_URL"
echo "- World slug: $WORLD_SLUG"
echo "- Smoke mode: $SMOKE_MODE"
echo "- Strict flags: $STRICT_FLAGS"
echo "- Run base smoke: $RUN_BASE_SMOKE"
echo "- Run test gates: $RUN_TEST_GATES"
echo "- Skip migrate: $SKIP_MIGRATE"
echo "- Skip cache: $SKIP_CACHE"
echo "- Dry run: $DRY_RUN"

if [[ "$SKIP_MIGRATE" == "0" ]]; then
  echo "[1/3] Running migrations..."
  run_cmd "$PHP_BIN" artisan migrate --force
else
  echo "[1/3] Skipping migrations (--skip-migrate)."
fi

if [[ "$SKIP_CACHE" == "0" ]]; then
  echo "[2/3] Refreshing runtime cache..."
  run_cmd "$PHP_BIN" artisan optimize:clear
  run_cmd "$PHP_BIN" artisan config:cache
else
  echo "[2/3] Skipping cache refresh (--skip-cache)."
fi

echo "[3/3] Running phase-a smoke gate..."
if ! run_phase_a_smoke; then
  echo "NO-GO: Phase-A gate failed. Stop rollout."
  exit 1
fi

echo "GO: Phase-A rollout flow passed."
