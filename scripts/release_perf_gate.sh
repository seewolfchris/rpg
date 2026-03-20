#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

RUN_BENCHMARK="${PERF_GATE_RUN_BENCHMARK:-1}"
SCENARIO="${PERF_GATE_SCENARIO:-default}"
LATEST_OUT="${PERF_LATEST_OUT:-docs/PERFORMANCE-POSTS-LATEST-BY-ID-LATEST.md}"
GATE_OUT="${PERF_GATE_OUT:-docs/PERFORMANCE-POSTS-LATEST-BY-ID-GATE-LATEST.md}"
GATE_ARCHIVE_OUT="${PERF_GATE_ARCHIVE_OUT:-}"
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

is_numeric() {
  local value="$1"
  [[ "$value" =~ ^[0-9]+([.][0-9]+)?$ ]]
}

absolute_path() {
  local path="$1"

  if [[ "$path" == /* ]]; then
    printf '%s\n' "$path"
    return
  fi

  printf '%s\n' "$PROJECT_ROOT/$path"
}

to_repo_relative() {
  local path="$1"

  if [[ "$path" == "$PROJECT_ROOT/"* ]]; then
    path="${path#"$PROJECT_ROOT/"}"
  fi

  printf '%s\n' "$path"
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

extract_scenario_metric_ms() {
  local file="$1"
  local scenario="$2"
  local metric="$3"

  awk -v scenario="$scenario" -v metric="$metric" '
    index($0, "### `"scenario"`") == 1 { in_scenario = 1; next }
    in_scenario && /^### / { in_scenario = 0 }
    in_scenario && /^## / { in_scenario = 0 }
    in_scenario && index($0, "- " metric ": `") == 1 {
      count = split($0, parts, "`")
      if (count >= 3) {
        value = parts[2]
        sub(/ ms$/, "", value)
        print value
        exit
      }
    }
  ' "$file"
}

extract_gate_status() {
  local file="$1"

  awk '
    index($0, "- Status: `") == 1 {
      count = split($0, parts, "`")
      if (count >= 3) {
        print parts[2]
        exit
      }
    }
  ' "$file"
}

extract_gate_median_ms() {
  local file="$1"

  awk '
    index($0, "- Median latency: `") == 1 || index($0, "- Median-Proxy (avg): ") == 1 {
      count = split($0, parts, "`")
      if (count >= 3) {
        value = parts[2]
        sub(/ ms$/, "", value)
        print value
        exit
      }
    }
  ' "$file"
}

extract_gate_p99_ms() {
  local file="$1"

  awk '
    index($0, "- P99 latency: `") == 1 || index($0, "- P99-Proxy (p95): ") == 1 {
      count = split($0, parts, "`")
      if (count >= 3) {
        value = parts[2]
        sub(/ ms$/, "", value)
        print value
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

is_le() {
  local value="$1"
  local threshold="$2"

  awk -v value="$value" -v threshold="$threshold" '
    BEGIN {
      if (value <= threshold) {
        exit 0
      }

      exit 1
    }
  '
}

median_of_three() {
  local first="$1"
  local second="$2"
  local third="$3"

  awk -v a="$first" -v b="$second" -v c="$third" '
    BEGIN {
      values[1] = a
      values[2] = b
      values[3] = c

      for (i = 1; i <= 3; i++) {
        for (j = i + 1; j <= 3; j++) {
          if (values[i] > values[j]) {
            tmp = values[i]
            values[i] = values[j]
            values[j] = tmp
          }
        }
      }

      printf "%.3f", values[2]
    }
  '
}

ratio_percent() {
  local current="$1"
  local previous="$2"

  awk -v current="$current" -v previous="$previous" '
    BEGIN {
      if (previous <= 0) {
        print ""
        exit
      }

      printf "%.2f", (current / previous) * 100
    }
  '
}

format_ms() {
  local value="$1"

  awk -v value="$value" '
    BEGIN {
      printf "%.3f", value
    }
  '
}

require_clean_tree_for_archive() {
  local -a allowed_paths=("$@")
  local status_line=""
  local path=""
  local allowed="0"

  while IFS= read -r status_line; do
    [[ -z "$status_line" ]] && continue

    path="${status_line:3}"
    allowed="0"

    for allowed_path in "${allowed_paths[@]}"; do
      if [[ -n "$allowed_path" && "$path" == "$allowed_path" ]]; then
        allowed="1"
        break
      fi
    done

    if [[ "$allowed" != "1" ]]; then
      echo "ERROR: clean-tree check failed before report archive copy. Unexpected change: $status_line"
      exit 1
    fi
  done < <(git status --porcelain)
}

require_cmd awk
require_cmd date
require_cmd git
require_cmd cp

require_decimal "$WARN_AVG_PCT" "PERF_WARN_AVG_PCT"
require_decimal "$WARN_P95_PCT" "PERF_WARN_P95_PCT"
require_decimal "$FAIL_AVG_PCT" "PERF_FAIL_AVG_PCT"
require_decimal "$FAIL_P95_PCT" "PERF_FAIL_P95_PCT"

LATEST_OUT_ABS="$(absolute_path "$LATEST_OUT")"
GATE_OUT_ABS="$(absolute_path "$GATE_OUT")"
GATE_ARCHIVE_OUT_ABS=""
if [[ -n "$GATE_ARCHIVE_OUT" ]]; then
  GATE_ARCHIVE_OUT_ABS="$(absolute_path "$GATE_ARCHIVE_OUT")"
fi

ensure_parent_dir "$GATE_OUT_ABS"
if [[ -n "$GATE_ARCHIVE_OUT_ABS" ]]; then
  ensure_parent_dir "$GATE_ARCHIVE_OUT_ABS"
fi

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

current_median_ms="$(extract_scenario_metric_ms "$LATEST_OUT_ABS" "$SCENARIO" "median")"
current_p99_ms="$(extract_scenario_metric_ms "$LATEST_OUT_ABS" "$SCENARIO" "p99")"
fallback_median=false
fallback_p99=false

if [[ -z "$current_median_ms" ]]; then
  current_median_ms="$(extract_scenario_metric_ms "$LATEST_OUT_ABS" "$SCENARIO" "avg")"
  fallback_median=true
fi

if [[ -z "$current_p99_ms" ]]; then
  current_p99_ms="$(extract_scenario_metric_ms "$LATEST_OUT_ABS" "$SCENARIO" "p95")"
  fallback_p99=true
fi

if ! is_numeric "$current_median_ms" || ! is_numeric "$current_p99_ms"; then
  echo "ERROR: could not resolve median/p99 metrics from benchmark latest for scenario '${SCENARIO}'"
  exit 1
fi

fallback_active=false
if [[ "$fallback_median" == true || "$fallback_p99" == true ]]; then
  fallback_active=true
fi

median_limit_pct="95"
p99_limit_pct="110"
if [[ "$fallback_active" == true ]]; then
  median_limit_pct="90"
  p99_limit_pct="105"
fi

declare -a history_statuses=()
declare -a history_medians=()
declare -a history_p99s=()
declare -a history_files=()

while IFS= read -r gate_file; do
  history_files+=("$gate_file")
done < <(find docs -maxdepth 1 -type f -name 'PERFORMANCE-POSTS-LATEST-BY-ID-GATE-*.md' ! -name 'PERFORMANCE-POSTS-LATEST-BY-ID-GATE-LATEST.md' | sort)

for gate_file in "${history_files[@]-}"; do
  [[ -z "$gate_file" ]] && continue
  file_status="$(extract_gate_status "$gate_file")"
  [[ -z "$file_status" ]] && continue

  file_median="$(extract_gate_median_ms "$gate_file")"
  file_p99="$(extract_gate_p99_ms "$gate_file")"

  history_statuses+=("$file_status")
  history_medians+=("$file_median")
  history_p99s+=("$file_p99")
done

history_statuses+=("$status")
history_medians+=("$current_median_ms")
history_p99s+=("$current_p99_ms")

records_count="${#history_statuses[@]}"
last3_statuses_line="insufficient_history"
all_last3_green=false
if [[ "$records_count" -ge 3 ]]; then
  s0="${history_statuses[$((records_count - 1))]}"
  s1="${history_statuses[$((records_count - 2))]}"
  s2="${history_statuses[$((records_count - 3))]}"
  last3_statuses_line="${s2}/${s1}/${s0}"

  if [[ "$s0" == "GRUEN" && "$s1" == "GRUEN" && "$s2" == "GRUEN" ]]; then
    all_last3_green=true
  fi
fi

have_windows=false
median_ratio_pct=""
p99_ratio_pct=""
current_window_median=""
previous_window_median=""
current_window_p99=""
previous_window_p99=""

if [[ "$records_count" -ge 6 ]]; then
  m0="${history_medians[$((records_count - 1))]}"
  m1="${history_medians[$((records_count - 2))]}"
  m2="${history_medians[$((records_count - 3))]}"
  m3="${history_medians[$((records_count - 4))]}"
  m4="${history_medians[$((records_count - 5))]}"
  m5="${history_medians[$((records_count - 6))]}"

  p0="${history_p99s[$((records_count - 1))]}"
  p1="${history_p99s[$((records_count - 2))]}"
  p2="${history_p99s[$((records_count - 3))]}"
  p3="${history_p99s[$((records_count - 4))]}"
  p4="${history_p99s[$((records_count - 5))]}"
  p5="${history_p99s[$((records_count - 6))]}"

  if is_numeric "$m0" && is_numeric "$m1" && is_numeric "$m2" && is_numeric "$m3" && is_numeric "$m4" && is_numeric "$m5" \
    && is_numeric "$p0" && is_numeric "$p1" && is_numeric "$p2" && is_numeric "$p3" && is_numeric "$p4" && is_numeric "$p5"; then
    current_window_median="$(median_of_three "$m0" "$m1" "$m2")"
    previous_window_median="$(median_of_three "$m3" "$m4" "$m5")"
    current_window_p99="$(median_of_three "$p0" "$p1" "$p2")"
    previous_window_p99="$(median_of_three "$p3" "$p4" "$p5")"

    if is_gt "$previous_window_median" "0" && is_gt "$previous_window_p99" "0"; then
      median_ratio_pct="$(ratio_percent "$current_window_median" "$previous_window_median")"
      p99_ratio_pct="$(ratio_percent "$current_window_p99" "$previous_window_p99")"

      if [[ -n "$median_ratio_pct" && -n "$p99_ratio_pct" ]]; then
        have_windows=true
      fi
    fi
  fi
fi

hint_decision="FORCE_INDEX=0"
hint_reason="insufficient_history"
if [[ "$all_last3_green" == false ]]; then
  hint_reason="last_3_not_all_green"
elif [[ "$have_windows" == false ]]; then
  hint_reason="insufficient_metric_history"
else
  median_condition_met=false
  p99_condition_met=false

  if is_le "$median_ratio_pct" "$median_limit_pct"; then
    median_condition_met=true
  fi

  if is_le "$p99_ratio_pct" "$p99_limit_pct"; then
    p99_condition_met=true
  fi

  if [[ "$median_condition_met" == true && "$p99_condition_met" == true ]]; then
    hint_decision="FORCE_INDEX=1"
    hint_reason="all_conditions_met"
  else
    hint_reason="threshold_violation"
  fi
fi

report_exit_code="0"

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
  echo "- Exit-Code: \`${report_exit_code}\`"
  echo "- Status: \`$status\`"
  if [[ -n "$avg_delta_pct" ]]; then
    echo "- avg delta vs baseline: \`$avg_delta_pct\`"
  fi
  if [[ -n "$p95_delta_pct" ]]; then
    echo "- p95 delta vs baseline: \`$p95_delta_pct\`"
  fi
  echo "- Median latency: \`$(format_ms "$current_median_ms") ms\`"
  echo "- P99 latency: \`$(format_ms "$current_p99_ms") ms\`"
  echo "- Reason: $reason"
  echo
  echo "## Hint-Entscheidung"
  echo "- Hint-Entscheidung: \`${hint_decision}\`"
  echo "- Begründung: ${hint_reason}"
  echo "- Letzte 3 Gates: \`${last3_statuses_line}\`"

  if [[ "$have_windows" == true ]]; then
    if [[ "$fallback_active" == true ]]; then
      echo "- WARN: Median/P99 nicht verfügbar -> Fallback avg->Median, p95->P99"
      echo "- Median-Proxy (avg): $(format_ms "$current_window_median") ms (${median_ratio_pct}% vom Vor) → Schwellen angepasst: <=90%"
      echo "- P99-Proxy (p95): $(format_ms "$current_window_p99") ms (${p99_ratio_pct}% vom Vor) → Schwellen angepasst: <=105%"
    else
      echo "- Median latency (Window): $(format_ms "$current_window_median") ms (${median_ratio_pct}% vom Vor) -> Schwelle <=95%"
      echo "- P99 latency (Window): $(format_ms "$current_window_p99") ms (${p99_ratio_pct}% vom Vor) -> Schwelle <=110%"
    fi
  else
    if [[ "$fallback_active" == true ]]; then
      echo "- WARN: Median/P99 nicht verfügbar -> Fallback avg->Median, p95->P99"
      echo "- Median-Proxy (avg): n/a ms (n/a% vom Vor) → Schwellen angepasst: <=90%"
      echo "- P99-Proxy (p95): n/a ms (n/a% vom Vor) → Schwellen angepasst: <=105%"
    else
      echo "- Median latency (Window): n/a"
      echo "- P99 latency (Window): n/a"
    fi
  fi

  echo
  echo "## Interpretation"
  echo "- \`GRUEN\`: Release kann ohne Performance-Sondermassnahmen weiterlaufen."
  echo "- \`GELB\`: Release moeglich, aber Delta beobachten und bei Bedarf erneut messen."
  echo "- \`ROT\`: Report-only Signal; Skript endet nur bei technischen Fehlern mit non-zero."
} >"$GATE_OUT_ABS"

if [[ -n "$GATE_ARCHIVE_OUT_ABS" ]]; then
  allowed_latest="$(to_repo_relative "$LATEST_OUT_ABS")"
  allowed_gate="$(to_repo_relative "$GATE_OUT_ABS")"
  allowed_source="$(to_repo_relative "$source_report")"

  require_clean_tree_for_archive "$allowed_latest" "$allowed_gate" "$allowed_source"
  cp "$GATE_OUT_ABS" "$GATE_ARCHIVE_OUT_ABS"
  echo "Saved gate archive: $GATE_ARCHIVE_OUT_ABS"
fi

echo "Performance gate status: $status"
if [[ -n "$avg_delta_pct" && -n "$p95_delta_pct" ]]; then
  echo "  avg delta: $avg_delta_pct"
  echo "  p95 delta: $p95_delta_pct"
fi
echo "Saved gate report: $GATE_OUT_ABS"

echo "Hint decision: $hint_decision ($hint_reason)"

exit 0
