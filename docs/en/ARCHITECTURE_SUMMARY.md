# C76-RPG Architecture Summary (EN)

This document is a summary only, not a second source of truth.

- Source of truth: [docs/ARCHITECTURE.md](../ARCHITECTURE.md)
- Release, development, live, and gate status: [docs/STATUS.md](../STATUS.md)

## 1. Core engineering standard

- Mutations are Actions-first.
- Action signatures are model-first by default.
- Mutating actions are `final`.
- Controllers stay thin: `authorize()` + action call + response.
- Persistence, transactions, and locking do not belong in controllers.
- Non-final Actions under `app/Actions` must be explicitly classified as read-only by the architecture guardrail.

## 2. Guardrails

- PHPStan controller guardrails enforce:
- no direct persistence in controllers
- no transaction/lock logic in controllers
- `composer analyse` is a CI gate for these rules

## 3. Authorization and role semantics

- Platform roles: `admin`, `player` (`UserRole`).
- Campaign roles: `gm`, `trusted_player`, `player` (`CampaignMembershipRole`) via `campaign_memberships`.
- Campaign owner is separate (`campaigns.owner_id`).
- `User::isGmOrAdmin()` is a deprecated legacy compatibility bridge and currently maps to `isAdmin()`.
- Campaign-scoped authorization should use `CampaignAccess` and policies.

## 4. Concurrency and reliability posture

- Critical race-prone paths are protected by DB constraints and transaction-safe action flows.
- Existing MySQL concurrency and critical test groups cover invitation, world update, reaction, and webpush-sensitive paths.
- Consolidation work should remain incremental and characterization-test first.
