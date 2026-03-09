# ROADMAP - C76-RPG (6 Monate + Multi-Welt-Rollout)

Status: Stabilisierung abgeschlossen, Multi-Welt-Umbau umgesetzt  
Stand: 2026-03-09

## Zielbild
- Stabile, wartbare Release-Beta mit verlässlicher Delivery.
- Plattform ist nicht mehr auf eine einzelne Welt festgelegt.
- WIP-Limit bleibt: `1 Feature-Task + 1 Bugfix`.

## Quality Gates (jede Iteration)
- `php artisan test --without-tty --do-not-cache-result` ist gruen.
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

## Implementierte Kernartefakte
- ADR: `docs/adr/2026-03-08-post-scene-domain-services.md`
- Domaenenservices: `app/Domain/Post/*`, `app/Domain/Scene/*`, `app/Domain/Campaign/CampaignParticipantResolver.php`
- Multi-Welt:
  - `app/Models/World.php`
  - `database/migrations/2026_03_09_120000_create_worlds_table.php`
  - `database/migrations/2026_03_09_120100_add_world_context_to_core_tables.php`
  - `app/Http/Controllers/WorldController.php`
  - `app/Http/Controllers/WorldAdminController.php`
  - `app/Http/Middleware/ApplyWorldContext.php`
- Delivery:
  - `.github/workflows/ci.yml`
  - `scripts/release_smoke.sh` (inkl. Weltkontext- und Legacy-Redirect-Checks)
- Observability:
  - `app/Http/Middleware/AttachRequestId.php`
  - `app/Support/Observability/StructuredLogger.php`
  - `docs/OPERATIONS_RUNBOOK.md`

## Aktueller Verifikationsstand (2026-03-09)
- `php artisan test --without-tty --do-not-cache-result` -> **133 passed, 672 assertions**
- `npm run build` -> **gruen**
- `composer analyse` -> im CI-Gate enthalten

## Compliance und Betrieb
- Rechtliche Verlinkung zentral auf:
  - `https://c76.org/impressum/`
  - `https://c76.org/datenschutz/`
- Footer vereinheitlicht auf allen sichtbaren Seiten.
- Repo-Lizenz klar als proprietaer (`LICENSE`, Composer-Metadaten).
- Alpine/Frontend ohne externe CDN-Abhaengigkeit (lokal gehostet).

## Parking Lot (bewusst nicht jetzt)
- Echten Web-Push-Stack (VAPID/Subscription-Management) nur bei Bedarf als Folgeprojekt.
- Realtime/WebSockets.
- Externe Media/CDN-Optimierung.

## Naechste Schritte
1. Staging/Prod-Smoke fuer Multi-Welt-Flows (`/welten`, `/w/{world}/campaigns`, Legacy-Redirects) mit erweitertem Smoke-Skript vollstaendig protokollieren.
2. Performance-Pass auf Weltkontext-Queries in Staging/Prod mit Realdaten finalisieren (lokaler Initialpass + Command `perf:world-hotpaths` dokumentiert in `docs/PERFORMANCE-PASS-2026-03-09.md`, Protokollvorlage: `docs/PERFORMANCE-PASS-STAGING-PROD.md`).
3. Optional: Admin-UX fuer Welt-Sortierung/Deaktivierung weiter scharfziehen.
