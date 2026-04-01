#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

usage() {
  cat <<'USAGE'
Usage:
  scripts/release_flow.sh <version> [--world <slug>] [--iter <n>] [--archive] [--dry-run] [--skip-perf] [--help]

Examples:
  scripts/release_flow.sh v0.25-beta --world chroniken-der-asche --archive
  scripts/release_flow.sh v0.25 --skip-perf
  scripts/release_flow.sh v0.25-beta --dry-run --iter 500 --archive

Description:
  Standard-Release-Flow (endet vor Deploy):
    1) Clean tree check
    2) release_prepare + lokale Quality Gates
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

echo "[1/13] Clean tree check..."
require_clean_tree

echo "[2/13] Prepare release metadata..."
prepare_args=(--version "$version" --build "$build")
if [[ "$dry_run" == true ]]; then
  prepare_args+=(--dry-run)
fi
scripts/release_prepare.sh "${prepare_args[@]}"

echo "[3/13] Validate composer..."
composer validate --strict

echo "[4/13] Static analysis..."
composer analyse

echo "[5/13] Backend tests..."
php artisan test --without-tty --do-not-cache-result

echo "[6/13] Frontend JS tests..."
npm run test:js

echo "[7/13] Frontend build..."
npm run build

echo "[8/13] Smoke checks (artisan mode)..."
SMOKE_MODE=artisan scripts/release_smoke.sh

if [[ "$dry_run" == true ]]; then
  echo "[9/13] Dry-run active: stop before commit/push/tag/perf."
  exit 0
fi

echo "[9/13] Commit release changes..."
git add -A
if git diff --cached --quiet; then
  echo "ERROR: no staged changes to commit after release preparation"
  exit 1
fi
git commit -m "chore(release): ${version}"

perf_archive_out=""
perf_ran="no"

if [[ "$skip_perf" == true ]]; then
  echo "[10/13] Perf gate skipped (--skip-perf)."
else
  echo "[10/13] Clean tree check before Perf-Gate..."
  require_clean_tree

  if [[ "$archive" == true ]]; then
    perf_archive_out="docs/PERFORMANCE-POSTS-LATEST-BY-ID-GATE-$(date -u +%Y%m%dT%H%M%SZ).md"
  fi

  if [[ -n "$perf_archive_out" ]]; then
    PERF_WORLD_SLUG="$world" \
    PERF_ITERATIONS="$iter" \
    PERF_GATE_ARCHIVE_OUT="$perf_archive_out" \
    scripts/release_perf_gate.sh
  else
    PERF_WORLD_SLUG="$world" \
    PERF_ITERATIONS="$iter" \
    scripts/release_perf_gate.sh
  fi

  perf_ran="yes"
fi

echo "[11/13] Push main..."
git push origin main

echo "[12/13] Clean tree check before git tag..."
require_clean_tree
git tag "$version"

echo "[13/13] Clean tree check before git push --tags..."
require_clean_tree
git push origin --tags

echo
echo "Release flow completed successfully."
echo "- Version: ${version}"
echo "- Build: ${build}"
echo "- Perf run: ${perf_ran}"
if [[ -n "$perf_archive_out" ]]; then
  echo "- Perf archive: ${perf_archive_out}"
fi
