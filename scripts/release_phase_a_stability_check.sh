#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

usage() {
  cat <<'USAGE'
Usage:
  scripts/release_phase_a_stability_check.sh [options]

Description:
  Runs the daily 3-5 day stability checks after Phase-A rollout.

Checks:
  1) ImmersionReadabilityFeatureTest
  2) RelativeTimeComponentTest
  3) JS draft autosave tests
  4) composer analyse (optional)
  5) phase-a smoke (optional, no test-gates)

Options:
  --base-url <url>            Base URL for optional smoke (default: http://127.0.0.1:8000)
  --world-slug <slug>         World slug for optional smoke (default: WORLD_DEFAULT_SLUG)
  --smoke-mode <http|artisan> Smoke mode for optional smoke (default: artisan)
  --skip-analyse              Skip composer analyse
  --skip-smoke                Skip phase-a smoke check
  --report-out <path>         Optional markdown report output
  --smoke-report-out <path>   Optional markdown report for nested phase-a smoke
  --dry-run                   Print commands only, do not execute
  -h, --help                  Show this help

Examples:
  scripts/release_phase_a_stability_check.sh --smoke-mode artisan --report-out docs/PHASE-A-STABILITY-DAY1.md
  scripts/release_phase_a_stability_check.sh --base-url "https://rpg.c76.org" --world-slug "<world-slug>" --smoke-mode http
USAGE
}

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

record_result() {
  local message="$1"
  RESULTS+=("$message")
}

write_report() {
  if [[ -z "$REPORT_OUT" ]]; then
    return
  fi

  if [[ "$DRY_RUN" == "1" ]]; then
    echo "Skipping stability report write in dry-run mode: $REPORT_OUT"
    return
  fi

  local report_dir
  report_dir="$(dirname "$REPORT_OUT")"
  mkdir -p "$report_dir"

  {
    echo "# Phase-A Stability Check"
    echo
    echo "- Generated at: \`$STARTED_AT\`"
    echo "- Base URL: \`$BASE_URL\`"
    echo "- World slug: \`$WORLD_SLUG\`"
    echo "- Smoke mode: \`$SMOKE_MODE\`"
    echo "- Analyse enabled: \`$RUN_ANALYSE\`"
    echo "- Smoke enabled: \`$RUN_SMOKE\`"
    echo
    echo "## Checks"
    for result in "${RESULTS[@]}"; do
      echo "- $result"
    done
  } >"$REPORT_OUT"

  echo "Saved stability report: $REPORT_OUT"
}

run_step() {
  local label="$1"
  shift
  echo "Running: $label"
  run_cmd "$@"
  record_result "PASS $label"
}

PHP_BIN="${PHP_BIN:-php}"
BASE_URL="${PHASE_A_BASE_URL:-${SMOKE_BASE_URL:-http://127.0.0.1:8000}}"
WORLD_SLUG="${PHASE_A_WORLD_SLUG:-${SMOKE_WORLD_SLUG:-${WORLD_DEFAULT_SLUG:-}}}"
SMOKE_MODE="${PHASE_A_STABILITY_SMOKE_MODE:-artisan}"
RUN_ANALYSE="1"
RUN_SMOKE="1"
REPORT_OUT="${PHASE_A_STABILITY_REPORT_OUT:-}"
SMOKE_REPORT_OUT="${PHASE_A_STABILITY_SMOKE_REPORT_OUT:-}"
DRY_RUN="0"
STARTED_AT="$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
declare -a RESULTS=()

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
    --skip-analyse)
      RUN_ANALYSE="0"
      shift
      ;;
    --skip-smoke)
      RUN_SMOKE="0"
      shift
      ;;
    --report-out)
      REPORT_OUT="${2:-}"
      shift 2
      ;;
    --smoke-report-out)
      SMOKE_REPORT_OUT="${2:-}"
      shift 2
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

if [[ "$RUN_SMOKE" == "1" && "$SMOKE_MODE" == "http" ]]; then
  require_non_empty "$WORLD_SLUG" "--world-slug/PHASE_A_WORLD_SLUG/WORLD_DEFAULT_SLUG" "Set --world-slug or WORLD_DEFAULT_SLUG (e.g. 'my-world')."
fi

require_cmd "$PHP_BIN"
require_cmd node

echo "[Phase A] Stability day-check"
echo "- Base URL: $BASE_URL"
echo "- World slug: $WORLD_SLUG"
echo "- Smoke mode: $SMOKE_MODE"
echo "- Analyse enabled: $RUN_ANALYSE"
echo "- Smoke enabled: $RUN_SMOKE"
echo "- Dry run: $DRY_RUN"

run_step \
  "php artisan test --filter=ImmersionReadabilityFeatureTest" \
  "$PHP_BIN" artisan test --without-tty --do-not-cache-result --filter=ImmersionReadabilityFeatureTest
run_step \
  "php artisan test --filter=RelativeTimeComponentTest" \
  "$PHP_BIN" artisan test --without-tty --do-not-cache-result --filter=RelativeTimeComponentTest
run_step \
  "node --test tests/js/post-editor-draft.test.mjs" \
  node --test tests/js/post-editor-draft.test.mjs

if [[ "$RUN_ANALYSE" == "1" ]]; then
  require_cmd composer
  run_step "composer analyse" composer analyse
else
  echo "Skipping composer analyse (--skip-analyse)."
  record_result "SKIP composer analyse"
fi

if [[ "$RUN_SMOKE" == "1" ]]; then
  local_rendered="PHASE_A_BASE_URL=$BASE_URL PHASE_A_WORLD_SLUG=$WORLD_SLUG PHASE_A_SMOKE_MODE=$SMOKE_MODE PHASE_A_STRICT_FLAGS=1 PHASE_A_RUN_BASE_SMOKE=1 PHASE_A_RUN_TEST_GATES=0"
  if [[ -n "$SMOKE_REPORT_OUT" ]]; then
    local_rendered="$local_rendered PHASE_A_REPORT_OUT=$SMOKE_REPORT_OUT"
  fi
  local_rendered="$local_rendered scripts/release_phase_a_smoke.sh"
  echo "Running: phase-a smoke gate (no test-gates)"
  echo "+ $local_rendered"

  if [[ "$DRY_RUN" != "1" ]]; then
    PHASE_A_BASE_URL="$BASE_URL" \
    PHASE_A_WORLD_SLUG="$WORLD_SLUG" \
    PHASE_A_SMOKE_MODE="$SMOKE_MODE" \
    PHASE_A_STRICT_FLAGS="1" \
    PHASE_A_RUN_BASE_SMOKE="1" \
    PHASE_A_RUN_TEST_GATES="0" \
    PHASE_A_REPORT_OUT="$SMOKE_REPORT_OUT" \
    scripts/release_phase_a_smoke.sh
  fi

  record_result "PASS phase-a smoke gate (no test-gates)"
else
  echo "Skipping phase-a smoke (--skip-smoke)."
  record_result "SKIP phase-a smoke gate"
fi

write_report
echo "GO: Phase-A stability day-check passed."
