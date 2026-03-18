#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

PHP_BIN="${PHP_BIN:-php}"
WORLD_SLUG="${PERF_WORLD_SLUG:-${WORLD_DEFAULT_SLUG:-}}"
ITERATIONS="${PERF_ITERATIONS:-400}"
REPORT_PREFIX="${PERF_REPORT_PREFIX:-docs/PERFORMANCE-POSTS-LATEST-BY-ID}"
REPORT_OUT="${PERF_REPORT_OUT:-${REPORT_PREFIX}-$(date -u '+%Y-%m-%d').md}"
LATEST_OUT="${PERF_LATEST_OUT:-docs/PERFORMANCE-POSTS-LATEST-BY-ID-LATEST.md}"
COMPARE_WITH="${PERF_COMPARE_WITH:-}"

require_cmd() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "ERROR: required command not found: $1"
    exit 1
  fi
}

require_numeric() {
  local value="$1"
  local label="$2"

  if ! [[ "$value" =~ ^[0-9]+$ ]]; then
    echo "ERROR: ${label} must be a positive integer (received: ${value})"
    exit 1
  fi

  if [[ "$value" -lt 1 ]]; then
    echo "ERROR: ${label} must be >= 1 (received: ${value})"
    exit 1
  fi
}

require_non_empty() {
  local value="$1"
  local label="$2"
  local hint="$3"

  if [[ -z "$value" ]]; then
    echo "ERROR: ${label} is empty. ${hint}"
    exit 1
  fi
}

ensure_parent_dir() {
  local path="$1"
  local parent
  parent="$(dirname "$path")"
  mkdir -p "$parent"
}

absolute_path() {
  local path="$1"

  if [[ "$path" == /* ]]; then
    printf '%s\n' "$path"
    return
  fi

  printf '%s\n' "$PROJECT_ROOT/$path"
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

extract_stat() {
  local file="$1"
  local scenario="$2"
  local metric="$3"

  awk -v scenario="$scenario" -v metric="$metric" '
    index($0, "## `"scenario"`") == 1 { in_scenario = 1; next }
    in_scenario && /^## / { in_scenario = 0 }
    in_scenario && index($0, "  - " metric ": `") == 1 {
      count = split($0, parts, "`")
      if (count >= 3) {
        result = parts[2]
        sub(/ ms$/, "", result)
        print result
        exit
      }
    }
  ' "$file"
}

extract_title() {
  local file="$1"
  local scenario="$2"

  awk -v scenario="$scenario" '
    index($0, "## `"scenario"` - ") == 1 {
      prefix = "## `"scenario"` - "
      print substr($0, length(prefix) + 1)
      exit
    }
  ' "$file"
}

has_scenario() {
  local file="$1"
  local scenario="$2"

  grep -Fq "## \`${scenario}\`" "$file"
}

list_scenarios() {
  local file="$1"

  awk '
    index($0, "## `") == 1 {
      count = split($0, parts, "`")
      if (count >= 3) {
        print parts[2]
      }
    }
  ' "$file"
}

format_ms_delta() {
  local baseline="$1"
  local current="$2"

  awk -v old="$baseline" -v now="$current" '
    BEGIN {
      delta = now - old
      sign = (delta > 0 ? "+" : "")
      printf "%s%.3f ms", sign, delta
    }
  '
}

format_percent_delta() {
  local baseline="$1"
  local current="$2"

  awk -v old="$baseline" -v now="$current" '
    BEGIN {
      if (old == 0) {
        print "n/a"
        exit
      }

      delta = ((now - old) / old) * 100
      sign = (delta > 0 ? "+" : "")
      printf "%s%.2f%%", sign, delta
    }
  '
}

is_lower() {
  local left="$1"
  local right="$2"

  awk -v left="$left" -v right="$right" '
    BEGIN {
      if (left < right) {
        exit 0
      }

      exit 1
    }
  '
}

resolve_baseline_report() {
  local output_abs="$1"

  if [[ -n "$COMPARE_WITH" ]]; then
    absolute_path "$COMPARE_WITH"
    return
  fi

  local latest=""

  while IFS= read -r file; do
    local abs
    abs="$(absolute_path "$file")"

    if [[ "$abs" == "$output_abs" ]]; then
      continue
    fi

    latest="$abs"
  done < <(find docs -maxdepth 1 -type f -name 'PERFORMANCE-POSTS-LATEST-BY-ID-20*.md' | sort)

  printf '%s\n' "$latest"
}

require_cmd "$PHP_BIN"
require_cmd awk
require_cmd find

require_numeric "$ITERATIONS" "PERF_ITERATIONS"
require_non_empty "$WORLD_SLUG" "PERF_WORLD_SLUG/WORLD_DEFAULT_SLUG" "Set PERF_WORLD_SLUG or WORLD_DEFAULT_SLUG (e.g. 'my-world')."

REPORT_OUT_ABS="$(absolute_path "$REPORT_OUT")"
LATEST_OUT_ABS="$(absolute_path "$LATEST_OUT")"

ensure_parent_dir "$REPORT_OUT_ABS"
ensure_parent_dir "$LATEST_OUT_ABS"

echo "Running posts.latest_by_id benchmark..."
echo "  world: $WORLD_SLUG"
echo "  iterations: $ITERATIONS"
echo "  report: $REPORT_OUT_ABS"

"$PHP_BIN" artisan perf:posts-latest-by-id-benchmark \
  --world="$WORLD_SLUG" \
  --iterations="$ITERATIONS" \
  --out="$REPORT_OUT_ABS"

if [[ ! -f "$REPORT_OUT_ABS" ]]; then
  echo "ERROR: benchmark report was not written: $REPORT_OUT_ABS"
  exit 1
fi

baseline_report=""
baseline_candidate="$(resolve_baseline_report "$REPORT_OUT_ABS")"
if [[ -n "$baseline_candidate" && -f "$baseline_candidate" ]]; then
  baseline_report="$baseline_candidate"
fi

generated_at="$(extract_meta "$REPORT_OUT_ABS" "Generated at")"
connection="$(extract_meta "$REPORT_OUT_ABS" "Connection")"
driver="$(extract_meta "$REPORT_OUT_ABS" "Driver")"
world_line="$(extract_meta "$REPORT_OUT_ABS" "World")"
iterations_line="$(extract_meta "$REPORT_OUT_ABS" "Iterations per scenario")"
sample_scenes="$(extract_meta "$REPORT_OUT_ABS" "Sample scenes")"

declare -a scenarios=()
while IFS= read -r scenario; do
  if [[ -n "$scenario" ]]; then
    scenarios+=("$scenario")
  fi
done < <(list_scenarios "$REPORT_OUT_ABS")

if [[ "${#scenarios[@]}" -eq 0 ]]; then
  echo "ERROR: no benchmark scenarios found in report: $REPORT_OUT_ABS"
  exit 1
fi

{
  echo "# posts.latest_by_id Benchmark Latest"
  echo
  echo "- Generated at: \`$generated_at\`"
  echo "- Source report: \`$REPORT_OUT\`"
  echo "- Connection: \`$connection\` (\`$driver\`)"
  echo "- World: \`$world_line\`"
  echo "- Iterations per scenario: \`$iterations_line\`"
  echo "- Sample scenes: \`$sample_scenes\`"
  echo
  echo "## Current run"

  for scenario in "${scenarios[@]}"; do
    title="$(extract_title "$REPORT_OUT_ABS" "$scenario")"
    runs="$(extract_stat "$REPORT_OUT_ABS" "$scenario" "runs")"
    avg="$(extract_stat "$REPORT_OUT_ABS" "$scenario" "avg")"
    p95="$(extract_stat "$REPORT_OUT_ABS" "$scenario" "p95")"
    min="$(extract_stat "$REPORT_OUT_ABS" "$scenario" "min")"
    max="$(extract_stat "$REPORT_OUT_ABS" "$scenario" "max")"

    echo "### \`$scenario\` - $title"
    echo "- runs: \`$runs\`"
    echo "- avg: \`${avg} ms\`"
    echo "- p95: \`${p95} ms\`"
    echo "- min: \`${min} ms\`"
    echo "- max: \`${max} ms\`"
    echo
  done

  if [[ -n "$baseline_report" ]]; then
    echo "## Delta vs baseline"
    echo "- Baseline report: \`${baseline_report#$PROJECT_ROOT/}\`"
    echo

    for scenario in "${scenarios[@]}"; do
      if ! has_scenario "$baseline_report" "$scenario"; then
        continue
      fi

      base_avg="$(extract_stat "$baseline_report" "$scenario" "avg")"
      base_p95="$(extract_stat "$baseline_report" "$scenario" "p95")"
      now_avg="$(extract_stat "$REPORT_OUT_ABS" "$scenario" "avg")"
      now_p95="$(extract_stat "$REPORT_OUT_ABS" "$scenario" "p95")"

      if [[ -z "$base_avg" || -z "$base_p95" || -z "$now_avg" || -z "$now_p95" ]]; then
        continue
      fi

      echo "### \`$scenario\`"
      echo "- avg delta: \`$(format_ms_delta "$base_avg" "$now_avg")\` (\`$(format_percent_delta "$base_avg" "$now_avg")\`)"
      echo "- p95 delta: \`$(format_ms_delta "$base_p95" "$now_p95")\` (\`$(format_percent_delta "$base_p95" "$now_p95")\`)"
      echo
    done
  fi

  if has_scenario "$REPORT_OUT_ABS" "default" && has_scenario "$REPORT_OUT_ABS" "force_index_scene_id_id"; then
    default_avg="$(extract_stat "$REPORT_OUT_ABS" "default" "avg")"
    default_p95="$(extract_stat "$REPORT_OUT_ABS" "default" "p95")"
    force_avg="$(extract_stat "$REPORT_OUT_ABS" "force_index_scene_id_id" "avg")"
    force_p95="$(extract_stat "$REPORT_OUT_ABS" "force_index_scene_id_id" "p95")"

    echo "## Current strategy hint"
    echo "- default avg: \`${default_avg} ms\`, p95: \`${default_p95} ms\`"
    echo "- force_index avg: \`${force_avg} ms\`, p95: \`${force_p95} ms\`"
    echo "- avg delta (force vs default): \`$(format_ms_delta "$default_avg" "$force_avg")\` (\`$(format_percent_delta "$default_avg" "$force_avg")\`)"
    echo "- p95 delta (force vs default): \`$(format_ms_delta "$default_p95" "$force_p95")\` (\`$(format_percent_delta "$default_p95" "$force_p95")\`)"

    if is_lower "$force_avg" "$default_avg" && is_lower "$force_p95" "$default_p95"; then
      echo "- Recommendation: \`FORCE INDEX\` bleibt messbar schneller im aktuellen Datensatz."
    elif is_lower "$force_avg" "$default_avg"; then
      echo "- Recommendation: \`FORCE INDEX\` ist im Durchschnitt schneller; p95 weiter beobachten."
    else
      echo "- Recommendation: kein Vorteil fuer \`FORCE INDEX\`; Default-Plan beibehalten."
    fi
  fi
} >"$LATEST_OUT_ABS"

echo "Saved benchmark report: $REPORT_OUT_ABS"
echo "Saved latest summary: $LATEST_OUT_ABS"
