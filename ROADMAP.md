# ROADMAP - C76-RPG (6 Monate + Multi-Welt-Rollout)

Status: Stabilisierung abgeschlossen, Multi-Welt-Umbau umgesetzt  
Stand: 2026-03-30

## Zielbild
- Stabile, wartbare Release-Beta mit verlässlicher Delivery.
- Plattform ist nicht mehr auf eine einzelne Welt festgelegt.
- WIP-Limit bleibt: `1 Feature-Task + 1 Bugfix`.

## Quality Gates (jede Iteration)
- `php artisan test --without-tty --do-not-cache-result` ist gruen.
- `node --test tests/js/*.mjs` ist gruen.
- `npm run build` ist gruen.
- `composer analyse` (Larastan/PHPStan) ist gruen.
- Keine Role/Policy-Regression in GM/Player-Flows.
- Mobile Basischeck (375px) fuer geaenderte Views.

## Paket 1 - Stabilisierung (12 x 2 Wochen)
| Sprint | Fokus | Status |
|---|---|---|
| 1 | Architektur-Baseline (ADR fuer Post/Scene-Domaene) | Erledigt |
| 2 | Post-Flow Entkopplung I (Store/Notify/Points) | Erledigt |
| 3 | Post-Flow Entkopplung II (Probe + Inventar-Award Services) | Erledigt |
| 4 | Scene-Flow Entkopplung (Read-Tracking, Jump-URL, Quick-Action Services) | Erledigt |
| 5 | CI-Grundlage (`.github/workflows/ci.yml`) | Erledigt |
| 6 | Release-Haertung (`scripts/release_smoke.sh`) | Erledigt |
| 7 | Datenbank-Performance (Hot-Path-Indizes) | Erledigt |
| 8 | Observability (strukturierte Logs + `X-Request-Id`) | Erledigt |
| 9 | UI-Foundation I (Design-Tokens + UI-Komponenten) | Erledigt |
| 10 | UI-Foundation II (Konsistenz-Pass Kernviews) | Erledigt |
| 11 | GM-Flow-Polish (Jump/Pin/Quick-Action UX) | Erledigt |
| 12 | Release-Kandidat (Doku-Konsolidierung + Bugburn) | Erledigt |

## Paket 2 - Multi-Welt-Plattform (Release A/B/C)
| Release | Fokus | Status |
|---|---|---|
| A | Datenmodell + Admin-Welten (`worlds`, FKs, Backfill) | Erledigt |
| B | Weltkontext-Routing `/w/{world}/...` + Legacy-`301` + Konsistenzhaertung | Erledigt |
| C | UX/Branding auf `C76-RPG`, Weltkatalog, Weltgetrennte Wissensbasis | Erledigt |

## Paket 3 - Architektur-Konsolidierung (laufend)
| Slice | Fokus | Status |
|---|---|---|
| A1 | Analyse-Haertung (PHPStan Level 5 -> 8) | Erledigt |
| A2.1 | `PostController` Update-Write-Flow in Action auslagern | Erledigt |
| A2.2 | `SceneController` entkoppeln | In Arbeit (threadPage delegiert auf Action) |
| A2.3 | `CharacterController` entkoppeln | In Arbeit (update delegiert auf Action) |

## Implementierte Kernartefakte
- ADR: `docs/adr/2026-03-08-post-scene-domain-services.md`
- Domaenenservices: `app/Domain/Post/*`, `app/Domain/Scene/*`, `app/Domain/Campaign/CampaignParticipantResolver.php`
- Post-Update-Action:
  - `app/Actions/Post/UpdatePostAction.php`
  - `tests/Unit/Actions/Post/UpdatePostActionTest.php`
- Scene-ThreadPage-Action:
  - `app/Actions/Scene/BuildSceneThreadPageDataAction.php`
  - `app/Actions/Scene/SceneThreadPageData.php`
  - `tests/Unit/Actions/Scene/BuildSceneThreadPageDataActionTest.php`
- Character-Update-Action:
  - `app/Actions/Character/UpdateCharacterAction.php`
  - `tests/Unit/Actions/Character/UpdateCharacterActionTest.php`
- Multi-Welt:
  - `app/Models/World.php`
  - `database/migrations/2026_03_09_120000_create_worlds_table.php`
  - `database/migrations/2026_03_09_120100_add_world_context_to_core_tables.php`
  - `app/Http/Controllers/WorldController.php`
  - `app/Http/Controllers/WorldAdminController.php`
  - `app/Http/Middleware/ApplyWorldContext.php`
- Delivery:
  - `.github/workflows/ci.yml`
  - `scripts/release_smoke.sh` (inkl. Weltkontext-/Global-Wissen-Checks und Markdown-Report via `SMOKE_REPORT_OUT`)
  - `docs/SMOKE-PASS-2026-03-09.md` (lokales Referenzprotokoll)
  - `docs/SMOKE-PASS-STAGING-PROD.md` (echter HTTP-Prod-Smoke)
- Immersion-Rollout:
  - `docs/IMMERSION_ROLLOUT_PHASED.md` (Phase A/B/C Betriebsablauf)
  - `scripts/release_phase_a_flow.sh` (Go/No-Go Rollout fuer Welle 1/2)
  - `scripts/release_phase_a_stability_check.sh` (Daily-Stability-Checks nach Phase A)
- Web Push:
  - `laravel-notification-channels/webpush` (VAPID, echte Push-Zustellung)
  - `app/Http/Controllers/Api/WebPushSubscriptionController.php` (`/api/webpush/subscribe`, `/api/webpush/unsubscribe`)
  - `database/migrations/2026_03_09_230000_create_push_subscriptions_table.php`
  - CI-Kompatibilitaet: WebPush-DB-Connection folgt standardmaessig `DB_CONNECTION` (optional via `WEBPUSH_DB_CONNECTION` uebersteuerbar)
- Performance:
  - `php artisan perf:posts-latest-by-id-benchmark` (neuer Benchmark-Command)
  - `scripts/perf_posts_latest_by_id.sh` (Recheck + Delta-Report)
  - `docs/PERFORMANCE-POSTS-LATEST-BY-ID-2026-03-09.md` (lokale Baseline)
  - `docs/PERFORMANCE-POSTS-LATEST-BY-ID-LATEST.md` (automatischer Vergleich zum letzten Lauf)
  - `docs/PERFORMANCE-POSTS-LATEST-BY-ID-STAGING-PROD.md` (Prod-Benchmark)
- Observability:
  - `app/Http/Middleware/AttachRequestId.php`
  - `app/Support/Observability/StructuredLogger.php`
  - `docs/OPERATIONS_RUNBOOK.md`

## Aktueller Verifikationsstand (2026-03-30)
- `php artisan test --without-tty --do-not-cache-result` -> **243 passed, 1227 assertions**
- `node --test tests/js/*.mjs` -> **18 passed**
- `npm run build` -> **gruen**
- `composer analyse` -> **keine Fehler (PHPStan Level 8)**
- GitHub Actions (`main`) -> **gruen**

## Compliance und Betrieb
- Rechtliche Verlinkung zentral auf:
  - `https://c76.org/impressum/`
  - `https://c76.org/datenschutz/`
- Footer vereinheitlicht auf allen sichtbaren Seiten.
- Repo-Lizenz klar als proprietaer (`LICENSE`, Composer-Metadaten).
- Alpine/Frontend ohne externe CDN-Abhaengigkeit (lokal gehostet).

## Parking Lot (bewusst nicht jetzt)
- Realtime/WebSockets.
- Externe Media/CDN-Optimierung.

## Naechste Schritte
1. `scripts/release_flow.sh --version ...` als Standard-Release-Ablauf etablieren.
2. Perf-Gate (`scripts/release_perf_gate.sh`) vor jedem Deploy gegen Zielsystem laufen lassen und Report ablegen.
3. Runtime-Hint fuer `posts.latest_by_id` anhand der Perf-Gate-Historie aktiv/aus halten.
