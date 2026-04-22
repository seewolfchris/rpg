#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

NON_CANONICAL_STATUS_FILES=(
  "README.md"
  "ROADMAP.md"
)

PROJECT_OVERVIEW_FILES=()
while IFS= read -r file; do
  PROJECT_OVERVIEW_FILES+=("$file")
done < <(git -c core.quotePath=false ls-files 'docs/PROJEKT-*BERSICHT.md')

if [[ "${#PROJECT_OVERVIEW_FILES[@]}" -ne 1 ]]; then
  echo "[status-drift] ERROR: expected exactly one tracked docs/PROJEKT-*BERSICHT.md file"
  exit 1
fi

NON_CANONICAL_STATUS_FILES+=("${PROJECT_OVERVIEW_FILES[0]}")

STATUS_PATTERNS=(
  'v[0-9]+\.[0-9]+([.-][0-9A-Za-z._-]+)?'
  '([0-9]+ passed|[0-9]+ assertions)'
  'Aktueller Verifikationsstand'
  'Statusdatum:'
  'Letzter Release-Eintrag:'
)

for file in "README.md" "ROADMAP.md"; do
  if ! grep -q 'docs/STATUS\.md' "$file"; then
    echo "[status-drift] ERROR: $file must reference docs/STATUS.md"
    exit 1
  fi
done

has_violation=0

for file in "${NON_CANONICAL_STATUS_FILES[@]}"; do
  if [[ ! -f "$file" ]]; then
    echo "[status-drift] ERROR: expected file not found: $file"
    exit 1
  fi

  for pattern in "${STATUS_PATTERNS[@]}"; do
    hits="$(grep -nE "$pattern" "$file" || true)"

    if [[ -n "$hits" ]]; then
      has_violation=1
      echo "[status-drift] ERROR: disallowed live-status data in $file (pattern: $pattern)"
      echo "$hits"
    fi
  done
done

if [[ "$has_violation" -ne 0 ]]; then
  exit 1
fi

echo "[status-drift] OK: live status remains canonical in docs/STATUS.md"
