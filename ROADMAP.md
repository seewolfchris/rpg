# ROADMAP - Chroniken der Asche (6 Monate)

Status: aktiv  
Stand: 2026-03-08

## Zielbild
- Von feature-starker Beta zu stabiler, wartbarer Release-Beta.
- Lieferbarkeit vor Feature-Inflation.
- WIP-Limit bleibt: `1 Feature-Task + 1 Bugfix`.

## Quality Gates (jede Iteration)
- `php artisan test --without-tty --do-not-cache-result` ist grün.
- `npm run build` ist grün.
- Keine Role/Policy-Regression in GM/Player-Flows.
- Mobile Basischeck (375px) für geänderte Views.

## Sprint-Plan (12 x 2 Wochen)
| Sprint | Fokus | Status |
|---|---|---|
| 1 | Architektur-Baseline (ADR für Post/Scene-Domäne) | Erledigt |
| 2 | Post-Flow Entkopplung I (Store/Notify/Points) | Erledigt |
| 3 | Post-Flow Entkopplung II (Probe + Inventar-Award Services) | Erledigt |
| 4 | Scene-Flow Entkopplung (Read-Tracking, Jump-URL, Quick-Action Services) | Erledigt |
| 5 | CI-Grundlage (`.github/workflows/ci.yml`) | Erledigt |
| 6 | Release-Härtung (`scripts/release_smoke.sh`) | Erledigt |
| 7 | Datenbank-Performance (Hot-Path-Indizes) | Erledigt |
| 8 | Observability (strukturierte Logs + `X-Request-Id`) | Erledigt |
| 9 | UI-Foundation I (Design-Tokens + UI-Komponenten) | Erledigt |
| 10 | UI-Foundation II (Konsistenz-Pass Kernviews) | Erledigt |
| 11 | GM-Flow-Polish (Jump/Pin/Quick-Action UX) | Erledigt |
| 12 | Release-Kandidat (Doku-Konsolidierung + Bugburn) | Erledigt |

## Implementierte Kernartefakte
- ADR: `docs/adr/2026-03-08-post-scene-domain-services.md`
- Domänenservices: `app/Domain/Post/*`, `app/Domain/Scene/*`, `app/Domain/Campaign/CampaignParticipantResolver.php`
- Observability:
  - `app/Http/Middleware/AttachRequestId.php`
  - `app/Support/Observability/StructuredLogger.php`
  - `docs/OPERATIONS_RUNBOOK.md`
- Delivery:
  - `.github/workflows/ci.yml`
  - `scripts/release_smoke.sh`
- DB-Performance:
  - `database/migrations/2026_03_08_120000_add_hot_path_indexes_for_post_scene_and_invitation_queries.php`

## Sprint-12 Abschluss (2026-03-08)
- Release-Kandidat ist abgeschlossen.
- Verifikation:
  - `php artisan test --without-tty --do-not-cache-result` -> **125 passed, 632 assertions**
  - `npm run build` -> **grün**
  - `scripts/release_smoke.sh` -> **grün** (artisan fallback mode in restriktiver Local-Sandbox)

## Parking Lot (weiterhin bewusst nicht jetzt)
- Push Notifications finalisieren.
- Realtime/WebSockets.
- Externe Media/CDN-Optimierung.
