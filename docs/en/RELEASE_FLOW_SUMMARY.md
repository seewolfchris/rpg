# C76-RPG Release Flow Summary (EN)

This document is a summary only, not a second source of truth.

- Source of truth: [docs/RELEASE-CHECKLISTE.md](../RELEASE-CHECKLISTE.md)
- Source of truth: [README.md](../../README.md)
- Current release and gate status: see [`docs/STATUS.md`](../STATUS.md). Do not duplicate live status data in this summary.

## 1. Standard release sequence

1. Update local `main` and finalize planned changes.
2. Run local quality checks before release tagging.
3. Update `APP_VERSION`/build metadata (scripted path preferred).
4. Commit and push release changes.
5. Deploy to target environment (generic host flow).
6. Run smoke checks.
7. Update project docs/status notes.
8. Record release protocol (version, commit, deploy time, smoke result, follow-ups).

## 2. Local quality baseline

```bash
php artisan optimize:clear
composer validate --strict
bash scripts/check_status_drift.sh
composer analyse
php artisan test --without-tty --do-not-cache-result tests/Feature/Architecture/ArchitectureGuardrailsTest.php
bash scripts/check_config_drift.sh
php artisan test --without-tty --do-not-cache-result --exclude-group=mysql-concurrency --exclude-group=mysql-critical
npm run test:js
npm run test:e2e
npm run build
SMOKE_MODE=artisan SMOKE_START_SERVER=0 scripts/release_smoke.sh
git diff --exit-code -- public/build public/js/character-sheet.global.js
```

## 3. Important release constraints

- Do not skip queue/redis production assumptions.
- Keep smoke checks as go/no-go signals.
- Keep docs synchronized when release flow, CI flow, or operational defaults change.
- `scripts/check_config_drift.sh` is intentionally warn-only/report-only in the consolidation phase and does not block.
- MySQL concurrency and critical groups run in the dedicated CI MySQL job; run them locally only when a matching MySQL environment is available.
