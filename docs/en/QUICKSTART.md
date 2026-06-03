# C76-RPG Quickstart (EN Summary)

This document is a summary only, not a second source of truth.

- Source of truth: [README.md](../../README.md)
- Source of truth: [docs/README.md](../README.md)
- Source of truth: [docs/RELEASE-CHECKLISTE.md](../RELEASE-CHECKLISTE.md)
- Source of truth: [docs/OPERATIONS_RUNBOOK.md](../OPERATIONS_RUNBOOK.md)
- Live status: see [`docs/STATUS.md`](../STATUS.md) as the source of truth for the current version, release state, and gate status.
- Last synced commit: `current release commit`

## 1. Local prerequisites

- PHP 8.5+
- Composer
- Node.js + npm
- MySQL/MariaDB recommended for production-like local testing

## 2. Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

## 3. Start app

```bash
# terminal 1
php artisan serve

# terminal 2
npm run dev
```

Open: `http://127.0.0.1:8000`

## 4. Fast local verification

```bash
php artisan optimize:clear
composer validate --strict
composer analyse
bash scripts/check_config_drift.sh
php artisan test --without-tty --do-not-cache-result --exclude-group=mysql-concurrency --exclude-group=mysql-critical
npm run test:js
npm run build
```

Notes:
- `scripts/check_config_drift.sh` is warn-only/report-only in PR-03 and always exits `0`.
- For full release flow and optional gates, use [docs/RELEASE-CHECKLISTE.md](../RELEASE-CHECKLISTE.md).
