#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

PHP_BIN="${PHP_BIN:-php}"
PHASE_A_BASE_URL="${PHASE_A_BASE_URL:-${SMOKE_BASE_URL:-http://127.0.0.1:8000}}"
PHASE_A_WORLD_SLUG="${PHASE_A_WORLD_SLUG:-${SMOKE_WORLD_SLUG:-${WORLD_DEFAULT_SLUG:-}}}"
PHASE_A_SMOKE_MODE="${PHASE_A_SMOKE_MODE:-${SMOKE_MODE:-http}}"
PHASE_A_RUN_BASE_SMOKE="${PHASE_A_RUN_BASE_SMOKE:-1}"
PHASE_A_STRICT_FLAGS="${PHASE_A_STRICT_FLAGS:-1}"
PHASE_A_RUN_TEST_GATES="${PHASE_A_RUN_TEST_GATES:-0}"
PHASE_A_REPORT_OUT="${PHASE_A_REPORT_OUT:-}"
PHASE_A_STARTED_AT="$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
declare -a PHASE_A_RESULTS=()

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

require_toggle() {
  local value="$1"
  local label="$2"

  if [[ "$value" != "0" && "$value" != "1" ]]; then
    echo "ERROR: $label must be 0 or 1 (received: $value)"
    exit 1
  fi
}

record_result() {
  local message="$1"
  PHASE_A_RESULTS+=("$message")
}

write_report() {
  if [[ -z "$PHASE_A_REPORT_OUT" ]]; then
    return
  fi

  local report_dir
  report_dir="$(dirname "$PHASE_A_REPORT_OUT")"
  mkdir -p "$report_dir"

  {
    echo "# Phase-A Smoke Report"
    echo
    echo "- Generated at: \`$PHASE_A_STARTED_AT\`"
    echo "- Base URL: \`$PHASE_A_BASE_URL\`"
    echo "- World slug: \`$PHASE_A_WORLD_SLUG\`"
    echo "- Smoke mode: \`$PHASE_A_SMOKE_MODE\`"
    echo "- Strict flags: \`$PHASE_A_STRICT_FLAGS\`"
    echo "- Run test gates: \`$PHASE_A_RUN_TEST_GATES\`"
    echo
    echo "## Checks"
    if [[ ${#PHASE_A_RESULTS[@]} -eq 0 ]]; then
      echo "- (no checks recorded)"
    else
      for result in "${PHASE_A_RESULTS[@]}"; do
        echo "- $result"
      done
    fi
  } >"$PHASE_A_REPORT_OUT"

  echo "Saved phase-a smoke report: $PHASE_A_REPORT_OUT"
}

read_feature_flag() {
  local flag_key="$1"

  "$PHP_BIN" -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class); $kernel->bootstrap(); $key = $argv[1] ?? ""; echo config($key) ? "true" : "false";' "$flag_key"
}

assert_flag_disabled() {
  local flag_key="$1"
  local value

  value="$(read_feature_flag "$flag_key")"
  if [[ "$value" == "false" ]]; then
    echo "OK   ${flag_key}=false"
    record_result "${flag_key}=false"
    return
  fi

  local message="${flag_key} should be false for phase A but is ${value}"
  if [[ "$PHASE_A_STRICT_FLAGS" == "1" ]]; then
    echo "ERROR: $message"
    exit 1
  fi

  echo "WARN: $message"
  record_result "WARN $message"
}

run_test_gate() {
  local label="$1"
  local filter="$2"

  echo "Running test gate: $label"
  "$PHP_BIN" artisan test --without-tty --do-not-cache-result --filter="$filter"
  record_result "PASS $label"
}

require_cmd "$PHP_BIN"
require_toggle "$PHASE_A_RUN_BASE_SMOKE" "PHASE_A_RUN_BASE_SMOKE"
require_toggle "$PHASE_A_STRICT_FLAGS" "PHASE_A_STRICT_FLAGS"
require_toggle "$PHASE_A_RUN_TEST_GATES" "PHASE_A_RUN_TEST_GATES"

if [[ "$PHASE_A_SMOKE_MODE" == "http" ]]; then
  require_non_empty "$PHASE_A_WORLD_SLUG" "PHASE_A_WORLD_SLUG/SMOKE_WORLD_SLUG/WORLD_DEFAULT_SLUG" "Set PHASE_A_WORLD_SLUG or WORLD_DEFAULT_SLUG (e.g. 'my-world')."
fi

echo "Running phase-a immersion smoke checks..."

if [[ "$PHASE_A_RUN_BASE_SMOKE" == "1" ]]; then
  SMOKE_BASE_URL="$PHASE_A_BASE_URL" \
  SMOKE_WORLD_SLUG="$PHASE_A_WORLD_SLUG" \
  SMOKE_MODE="$PHASE_A_SMOKE_MODE" \
  scripts/release_smoke.sh
  record_result "PASS base release smoke"
else
  echo "Skipping base release smoke (PHASE_A_RUN_BASE_SMOKE=0)."
  record_result "SKIP base release smoke"
fi

echo "Checking rollout flags for phase A..."
assert_flag_disabled "features.wave3.editor_preview"
assert_flag_disabled "features.wave3.draft_autosave"
assert_flag_disabled "features.wave4.mentions"
assert_flag_disabled "features.wave4.reactions"
assert_flag_disabled "features.wave4.active_characters_week"

if [[ "$PHASE_A_RUN_TEST_GATES" == "1" ]]; then
  echo "Preparing runtime for local test gates..."
  "$PHP_BIN" artisan optimize:clear >/dev/null
  record_result "PASS artisan optimize:clear before test gates"

  echo "Running immersion test gates..."
  run_test_gate \
    "scene create with mood/header/previous" \
    "ImmersionReadabilityFeatureTest::test_gm_can_create_scene_with_mood_header_image_and_previous_scene"
  run_test_gate \
    "scene previous scene scope guard" \
    "ImmersionReadabilityFeatureTest::test_scene_store_rejects_previous_scene_outside_campaign_scope"
  run_test_gate \
    "scene header image replace/remove" \
    "ImmersionReadabilityFeatureTest::test_scene_update_replaces_and_removes_header_image"
  run_test_gate \
    "scene thread renders previous link and ic-first defaults" \
    "ImmersionReadabilityFeatureTest::test_scene_show_renders_previous_scene_link_and_ic_first_markup"
  run_test_gate \
    "ic quote persistence and ooc guard" \
    "ImmersionReadabilityFeatureTest::test_post_ic_quote_is_persisted_and_rendered_while_ooc_quote_is_rejected"
  run_test_gate \
    "relative time component renders relative plus absolute" \
    "RelativeTimeComponentTest"
else
  echo "Skipping immersion test gates (PHASE_A_RUN_TEST_GATES=0)."
  record_result "SKIP immersion test gates"
fi

write_report
echo "Phase-a immersion smoke checks passed."
