#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

usage() {
  cat <<'USAGE'
Usage:
  scripts/release_flow.sh --version vX.XX-beta [--build <build>] [--update-dotenv]

Description:
  Runs the local release flow in fixed order:
    1) release_prepare
    2) quality gates (composer validate/analyse, tests, sw tests, build)
    3) performance gate
    4) artisan smoke checks
USAGE
}

require_cmd() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "ERROR: required command not found: $1"
    exit 1
  fi
}

VERSION=""
BUILD=""
UPDATE_DOTENV="0"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --version)
      VERSION="${2:-}"
      shift 2
      ;;
    --build)
      BUILD="${2:-}"
      shift 2
      ;;
    --update-dotenv)
      UPDATE_DOTENV="1"
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

if [[ -z "$VERSION" ]]; then
  echo "ERROR: --version is required."
  usage
  exit 1
fi

if [[ -z "$BUILD" ]]; then
  BUILD="$(git rev-parse --short HEAD 2>/dev/null || true)"
fi

if [[ -z "$BUILD" ]]; then
  echo "ERROR: could not resolve --build automatically."
  exit 1
fi

require_cmd composer
require_cmd php
require_cmd npm

echo "[1/8] Prepare release metadata..."
if [[ "$UPDATE_DOTENV" == "1" ]]; then
  scripts/release_prepare.sh --version "$VERSION" --build "$BUILD" --update-dotenv
else
  scripts/release_prepare.sh --version "$VERSION" --build "$BUILD"
fi

echo "[2/8] Validate composer..."
composer validate --strict

echo "[3/8] Static analysis..."
composer analyse

echo "[4/8] Backend tests..."
php artisan test --without-tty --do-not-cache-result

echo "[5/8] Service Worker tests..."
npm run test:sw

echo "[6/8] Frontend build..."
npm run build

echo "[7/8] Performance gate..."
scripts/release_perf_gate.sh

echo "[8/8] Smoke checks (artisan mode)..."
SMOKE_MODE=artisan scripts/release_smoke.sh

echo
echo "Release flow completed successfully."
echo "Next:"
echo "  git status"
echo "  git add -A && git commit -m \"release: ${VERSION}\" && git push origin main"

