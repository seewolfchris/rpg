#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

usage() {
  cat <<'USAGE'
Usage:
  scripts/release_flow.sh <version> [--world <slug>] [--iter <n>] [--archive] [--dry-run] [--skip-perf] [--enforce-perf] [--no-enforce-perf] [--skip-backend-tests] [--skip-js-tests] [--skip-e2e-tests] [--skip-build] [--skip-smoke] [--help]

Examples:
  scripts/release_flow.sh v0.26-beta --world chroniken-der-asche --archive
  scripts/release_flow.sh v0.26 --skip-perf
  scripts/release_flow.sh v0.26 --world chroniken-der-asche --enforce-perf
  scripts/release_flow.sh v0.26-beta --dry-run --iter 500 --archive

Description:
  Standard-Release-Flow (endet vor Deploy):
    1) Clean tree check
    2) release_prepare + lokale Quality Gates (Composer, Analyse, PHPUnit, JS, E2E, Build, Smoke)
    3) Commit release changes
    4) optional Perf-Gate
    5) push main + tag + push --tags
USAGE
}

require_cmd() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "ERROR: required command not found: $1"
    exit 1
  fi
}

require_clean_tree() {
  if [[ -n "$(git status --porcelain)" ]]; then
    echo "ERROR: Working tree is not clean."
    git status --short
    exit 1
  fi
}

require_positive_int() {
  local value="$1"
  local label="$2"

  if ! [[ "$value" =~ ^[0-9]+$ ]] || [[ "$value" -lt 1 ]]; then
    echo "ERROR: ${label} must be a positive integer (received: ${value})"
    exit 1
  fi
}

version=""
world=""
iter="400"
archive=false
dry_run=""
skip_perf=""
skip_backend_tests=""
skip_js_tests=""
skip_e2e_tests=""
skip_build=""
skip_smoke=""
perf_enforce=""

if [[ $# -eq 0 ]]; then
  usage
  exit 1
fi

while [[ $# -gt 0 ]]; do
  case "$1" in
    --world)
      world="${2:-}"
      shift 2
      ;;
    --iter)
      iter="${2:-}"
      shift 2
      ;;
    --archive)
      archive=true
      shift
      ;;
    --dry-run)
      dry_run=true
      shift
      ;;
    --skip-perf)
      skip_perf=true
      shift
      ;;
    --skip-backend-tests)
      skip_backend_tests=true
      shift
      ;;
    --skip-js-tests)
      skip_js_tests=true
      shift
      ;;
    --skip-e2e-tests)
      skip_e2e_tests=true
      shift
      ;;
    --skip-build)
      skip_build=true
      shift
      ;;
    --skip-smoke)
      skip_smoke=true
      shift
      ;;
    --enforce-perf)
      perf_enforce="1"
      shift
      ;;
    --no-enforce-perf)
      perf_enforce="0"
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    --*)
      echo "ERROR: unknown argument '$1'"
      usage
      exit 1
      ;;
    *)
      if [[ -z "$version" ]]; then
        version="$1"
        shift
      else
        echo "ERROR: unexpected positional argument '$1'"
        usage
        exit 1
      fi
      ;;
  esac
done

if [[ -z "$version" ]]; then
  echo "ERROR: <version> is required."
  usage
  exit 1
fi

[[ $version =~ ^v[0-9]+\.[0-9]+(-beta|-rc[0-9]+)?$ ]] || { echo "Ungültiges Versionsformat (erwartet: vX.Y oder vX.Y-beta/rcN)"; exit 1; }

if [[ -z "$perf_enforce" ]]; then
  if [[ "$version" =~ -beta$ || "$version" =~ -rc[0-9]+$ ]]; then
    perf_enforce="0"
  else
    perf_enforce="1"
  fi
fi

if [[ ! $skip_perf && ! $dry_run && -z $world ]]; then echo "--world <slug> erforderlich für echte Perf-Läufe"; exit 1; fi

require_positive_int "$iter" "--iter"

build="$(git rev-parse --short HEAD 2>/dev/null || true)"
if [[ -z "$build" ]]; then
  echo "ERROR: could not resolve build hash from git"
  exit 1
fi

require_cmd git
require_cmd composer
require_cmd php
require_cmd npm
require_cmd date

echo "[1/14] Clean tree check..."
require_clean_tree

echo "[2/14] Prepare release metadata..."
prepare_args=(--version "$version" --build "$build")
if [[ "$dry_run" == true ]]; then
  prepare_args+=(--dry-run)
fi
scripts/release_prepare.sh "${prepare_args[@]}"

echo "[3/14] Validate composer..."
composer validate --strict

echo "[4/14] Static analysis..."
composer analyse

echo "[5/14] Backend tests..."
if [[ "$skip_backend_tests" == true ]]; then
  echo "Skipping backend tests (--skip-backend-tests)."
else
  php artisan test --without-tty --do-not-cache-result
fi

echo "[6/14] Frontend JS tests..."
if [[ "$skip_js_tests" == true ]]; then
  echo "Skipping frontend JS tests (--skip-js-tests)."
else
  npm run test:js
fi

echo "[7/14] Browser E2E tests..."
if [[ "$skip_e2e_tests" == true ]]; then
  echo "Skipping browser E2E tests (--skip-e2e-tests)."
else
  npm run test:e2e
fi

echo "[8/14] Frontend build..."
if [[ "$skip_build" == true ]]; then
  echo "Skipping frontend build (--skip-build)."
else
  npm run build
fi

echo "[9/14] Smoke checks (artisan mode)..."
if [[ "$skip_smoke" == true ]]; then
  echo "Skipping smoke checks (--skip-smoke)."
else
  SMOKE_MODE=artisan scripts/release_smoke.sh
fi

if [[ "$dry_run" == true ]]; then
  echo "[10/14] Dry-run active: stop before commit/push/tag/perf."
  exit 0
fi

echo "[10/14] Commit release changes..."
git add -A
if git diff --cached --quiet; then
  echo "ERROR: no staged changes to commit after release preparation"
  exit 1
fi
git commit -m "chore(release): ${version}"

perf_archive_out=""
perf_ran="no"

if [[ "$skip_perf" == true ]]; then
  echo "[11/14] Perf gate skipped (--skip-perf)."
else
  echo "[11/14] Clean tree check before Perf-Gate..."
  require_clean_tree
  echo "Perf gate enforce mode: PERF_GATE_ENFORCE=${perf_enforce}"

  if [[ "$archive" == true ]]; then
    perf_archive_out="docs/PERFORMANCE-POSTS-LATEST-BY-ID-GATE-$(date -u +%Y%m%dT%H%M%SZ).md"
  fi

  if [[ -n "$perf_archive_out" ]]; then
    PERF_WORLD_SLUG="$world" \
    PERF_ITERATIONS="$iter" \
    PERF_GATE_ENFORCE="$perf_enforce" \
    PERF_GATE_ARCHIVE_OUT="$perf_archive_out" \
    scripts/release_perf_gate.sh
  else
    PERF_WORLD_SLUG="$world" \
    PERF_ITERATIONS="$iter" \
    PERF_GATE_ENFORCE="$perf_enforce" \
    scripts/release_perf_gate.sh
  fi

  perf_ran="yes"

  echo "[11/14] Commit Perf-Reports (falls geändert)..."
  perf_report_changes=()
  while IFS= read -r changed_path; do
    if [[ -n "$changed_path" ]]; then
      perf_report_changes+=("$changed_path")
    fi
  done < <(git status --porcelain | awk '/^.. docs\/PERFORMANCE-POSTS-LATEST-BY-ID.*\.md$/ {print substr($0,4)}')

  if [[ "${#perf_report_changes[@]}" -gt 0 ]]; then
    git add -- "${perf_report_changes[@]}"

    if ! git diff --cached --quiet; then
      git commit -m "chore(release): perf reports for ${version}"
    fi
  else
    echo "No perf report changes detected."
  fi
fi

echo "[12/14] Push main..."
git push origin main

echo "[13/14] Clean tree check before git tag..."
require_clean_tree
git tag "$version"

echo "[14/14] Clean tree check before git push --tags..."
require_clean_tree
git push origin --tags

echo
echo "Release flow completed successfully."
echo "- Version: ${version}"
echo "- Build: ${build}"
echo "- Perf run: ${perf_ran}"
echo "- Perf enforce mode: ${perf_enforce}"
if [[ -n "$perf_archive_out" ]]; then
  echo "- Perf archive: ${perf_archive_out}"
fi
