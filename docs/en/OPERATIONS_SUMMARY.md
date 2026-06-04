# C76-RPG Operations Summary (EN)

This document is a summary only, not a second source of truth.

- Source of truth: [docs/OPERATIONS_RUNBOOK.md](../OPERATIONS_RUNBOOK.md)
- Source of truth: [docs/RELEASE-CHECKLISTE.md](../RELEASE-CHECKLISTE.md)
- Release, development, live, and gate status: [docs/STATUS.md](../STATUS.md)

## 1. Production defaults (high level)

- `QUEUE_CONNECTION=redis`
- `CACHE_STORE=redis`
- `SESSION_DRIVER=redis`
- `SESSION_SECURE_COOKIE=true`
- `QUEUE_AFTER_COMMIT=true`
- `TRUSTED_PROXIES` must be explicitly set
- `SECURITY_HSTS_MAX_AGE > 0` (recommended `31536000`)

## 2. Incident basics

- Use `X-Request-Id` to correlate web responses and domain logs.
- Focus log searches on `request_id`, `user_id`, `scene_id`, `post_id`.
- Keep queue retry checks and webpush health checks ready as first response actions.

## 3. Queue, webpush, and offline notes

- Queue worker must run in production for async retries.
- Webpush failures (`404`/`410`) can trigger invalid subscription cleanup.
- Offline queue is browser-local data; auth boundary and logout cleanup behavior are part of operational checks.
- Immersive post images and scene content images use public storage URLs; confidential media should use controlled handouts.

## 4. Post-deploy minimum

- Run release smoke checks.
- Manually verify dashboard, scene access, and GM moderation entry points.
- If issues occur, capture `request_id` and follow the runbook incident flow.
