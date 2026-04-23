#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

RUN_FULL_BACKEND=0
RUN_BUILD_ARTIFACT_CHECK=0

print_help() {
  cat <<'EOF'
Usage: scripts/pre_push_check.sh [--full] [--with-build]

Options:
  --full        Run the full backend test command used in CI
  --with-build  Run npm build and verify committed frontend artifacts
  --help        Show this help
EOF
}

while (($# > 0)); do
  case "$1" in
    --full)
      RUN_FULL_BACKEND=1
      ;;
    --with-build)
      RUN_BUILD_ARTIFACT_CHECK=1
      ;;
    --help|-h)
      print_help
      exit 0
      ;;
    *)
      echo "[pre-push] Unknown option: $1" >&2
      print_help
      exit 2
      ;;
  esac
  shift
done

echo "[pre-push] composer analyse"
composer analyse

if [[ "$RUN_FULL_BACKEND" -eq 1 ]]; then
  echo "[pre-push] php artisan test (full backend suite without mysql-* groups)"
  php artisan test --without-tty --do-not-cache-result --exclude-group=mysql-concurrency --exclude-group=mysql-critical
else
  echo "[pre-push] php artisan test (focused role/authorization suite)"
  php artisan test --without-tty --do-not-cache-result \
    tests/Feature/Architecture/ArchitectureGuardrailsTest.php \
    tests/Feature/CampaignMembershipReadSwitchTest.php \
    tests/Feature/CampaignMembershipManagementTest.php \
    tests/Feature/CampaignGmContactFeatureTest.php \
    tests/Feature/CharacterManagementTest.php \
    tests/Feature/GmAccessTest.php \
    tests/Unit/Actions/Character/BuildCharacterIndexDataActionTest.php
fi

if [[ "$RUN_BUILD_ARTIFACT_CHECK" -eq 1 ]]; then
  echo "[pre-push] npm run build"
  npm run build

  echo "[pre-push] verify committed frontend artifacts"
  git diff --exit-code -- public/build public/js/character-sheet.global.js
fi

echo "[pre-push] ok"
