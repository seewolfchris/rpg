#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

RUN_BENCHMARK="${PERF_GATE_RUN_BENCHMARK:-1}"
SCENARIO="${PERF_GATE_SCENARIO:-default}"
LATEST_OUT="${PERF_LATEST_OUT:-docs/PERFORMANCE-POSTS-LATEST-BY-ID-LATEST.md}"
GATE_OUT="${PERF_GATE_OUT:-docs/PERFORMANCE-POSTS-LATEST-BY-ID-GATE-LATEST.md}"
WARN_AVG_PCT="${PERF_WARN_AVG_PCT:-10}"
WARN_P95_PCT="${PERF_WARN_P95_PCT:-15}"
FAIL_AVG_PCT="${PERF_FAIL_AVG_PCT:-25}"
FAIL_P95_PCT="${PERF_FAIL_P95_PCT:-35}"

require_cmd() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "ERROR: required command not found: $1"
    exit 1
  fi
}

require_decimal() {
  local value="$1"
  local label="$2"

  if ! [[ "$value" =~ ^[0-9]+([.][0-9]+)?$ ]]; then
    echo "ERROR: ${label} must be a positive decimal number (received: ${value})"
    exit 1
  fi
}

absolute_path() {
  local path="$1"

  if [[ "$path" == /* ]]; then
    printf '%s\n' "$path"
    return
  fi

  printf '%s\n' "$PROJECT_ROOT/$path"
}

ensure_parent_dir() {
  local path="$1"
  mkdir -p "$(dirname "$path")"
}

extract_meta() {
  local file="$1"
  local key="$2"

  awk -v key="$key" '
    $0 ~ "^- "key": `" {
      count = split($0, parts, "`")
      if (count >= 3) {
        print parts[2]
        exit
      }
    }
  ' "$file"
}

extract_delta_percent() {
  local file="$1"
  local scenario="$2"
  local metric="$3"

  awk -v scenario="$scenario" -v metric="$metric" '
    /^## Delta vs baseline/ { in_delta = 1; next }
    in_delta && /^## / { in_delta = 0 }
    in_delta && index($0, "### `"scenario"`") == 1 { in_scenario = 1; next }
    in_delta && in_scenario && /^### / { in_scenario = 0 }
    in_delta && in_scenario && index($0, "- " metric " delta: `") == 1 {
      count = split($0, parts, "`")
      if (count >= 5) {
        print parts[4]
        exit
      }
    }
  ' "$file"
}

strip_percent() {
  local value="$1"
  value="${value//%/}"
  printf '%s\n' "$value"
}

is_gt() {
  local value="$1"
  local threshold="$2"

  awk -v value="$value" -v threshold="$threshold" '
    BEGIN {
      if (value > threshold) {
        exit 0
      }

      exit 1
    }
  '
}

require_cmd awk
require_cmd date

require_decimal "$WARN_AVG_PCT" "PERF_WARN_AVG_PCT"
require_decimal "$WARN_P95_PCT" "PERF_WARN_P95_PCT"
require_decimal "$FAIL_AVG_PCT" "PERF_FAIL_AVG_PCT"
require_decimal "$FAIL_P95_PCT" "PERF_FAIL_P95_PCT"

LATEST_OUT_ABS="$(absolute_path "$LATEST_OUT")"
GATE_OUT_ABS="$(absolute_path "$GATE_OUT")"

ensure_parent_dir "$GATE_OUT_ABS"

if [[ "$RUN_BENCHMARK" == "1" ]]; then
  echo "Running benchmark recheck before gate evaluation..."
  scripts/perf_posts_latest_by_id.sh
fi

if [[ ! -f "$LATEST_OUT_ABS" ]]; then
  echo "ERROR: latest benchmark report not found: $LATEST_OUT_ABS"
  exit 1
fi

generated_at="$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
source_generated_at="$(extract_meta "$LATEST_OUT_ABS" "Generated at")"
source_report="$(extract_meta "$LATEST_OUT_ABS" "Source report")"

status="GELB"
reason="Keine Baseline-Deltas verfuegbar."
avg_delta_pct=""
p95_delta_pct=""

if grep -Fq "## Delta vs baseline" "$LATEST_OUT_ABS"; then
  avg_delta_pct="$(extract_delta_percent "$LATEST_OUT_ABS" "$SCENARIO" "avg")"
  p95_delta_pct="$(extract_delta_percent "$LATEST_OUT_ABS" "$SCENARIO" "p95")"

  if [[ -n "$avg_delta_pct" && -n "$p95_delta_pct" ]]; then
    avg_value="$(strip_percent "$avg_delta_pct")"
    p95_value="$(strip_percent "$p95_delta_pct")"

    if is_gt "$avg_value" "$FAIL_AVG_PCT" || is_gt "$p95_value" "$FAIL_P95_PCT"; then
      status="ROT"
      reason="Regression ueber Fail-Schwelle."
    elif is_gt "$avg_value" "$WARN_AVG_PCT" || is_gt "$p95_value" "$WARN_P95_PCT"; then
      status="GELB"
      reason="Regression ueber Warn-Schwelle."
    else
      status="GRUEN"
      reason="Keine relevante Regression gegen Baseline."
    fi
  else
    reason="Szenario '${SCENARIO}' hat keine vollstaendigen Delta-Werte."
  fi
fi

{
  echo "# posts.latest_by_id Release Perf Gate"
  echo
  echo "- Evaluated at: \`$generated_at\`"
  echo "- Benchmark latest: \`${LATEST_OUT}\`"
  echo "- Benchmark generated at: \`$source_generated_at\`"
  echo "- Source report: \`$source_report\`"
  echo "- Scenario: \`$SCENARIO\`"
  echo "- Thresholds:"
  echo "  - warn avg > \`${WARN_AVG_PCT}%\`"
  echo "  - warn p95 > \`${WARN_P95_PCT}%\`"
  echo "  - fail avg > \`${FAIL_AVG_PCT}%\`"
  echo "  - fail p95 > \`${FAIL_P95_PCT}%\`"
  echo
  echo "## Result"
  echo "- Status: \`$status\`"
  if [[ -n "$avg_delta_pct" ]]; then
    echo "- avg delta vs baseline: \`$avg_delta_pct\`"
  fi
  if [[ -n "$p95_delta_pct" ]]; then
    echo "- p95 delta vs baseline: \`$p95_delta_pct\`"
  fi
  echo "- Reason: $reason"
  echo
  echo "## Interpretation"
  echo "- \`GRUEN\`: Release kann ohne Performance-Sondermassnahmen weiterlaufen."
  echo "- \`GELB\`: Release moeglich, aber Delta beobachten und bei Bedarf erneut messen."
  echo "- \`ROT\`: Release-Blocker fuer diesen Hotpath, erst Ursache klaeren."
} >"$GATE_OUT_ABS"

echo "Performance gate status: $status"
if [[ -n "$avg_delta_pct" && -n "$p95_delta_pct" ]]; then
  echo "  avg delta: $avg_delta_pct"
  echo "  p95 delta: $p95_delta_pct"
fi
echo "Saved gate report: $GATE_OUT_ABS"

if [[ "$status" == "ROT" ]]; then
  exit 1
fi

