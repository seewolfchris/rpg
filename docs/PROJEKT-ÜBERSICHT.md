# C76-RPG - Projekt-Uebersicht

Stand: 2026-03-30  
Repository-Branch: `main`

## Quicklinks
- Einstieg und Setup: `README.md`
- Gesamt-Roadmap: `ROADMAP.md`
- Immersion-Architektur: `docs/IMMERSION-ARCHITEKTUR.md`
- Release-Ablauf: `docs/RELEASE-CHECKLISTE.md`
- Betrieb/Incident-Handling: `docs/OPERATIONS_RUNBOOK.md`
- Performance-Pass (Referenzlauf): `docs/PERFORMANCE-PASS-2026-03-09.md`
- Performance Staging/Prod: `docs/PERFORMANCE-PASS-STAGING-PROD.md`
- Benchmark `posts.latest_by_id` (letzter datierter Lauf): `docs/PERFORMANCE-POSTS-LATEST-BY-ID-2026-03-20.md`
- Benchmark `posts.latest_by_id` Latest/Deltas: `docs/PERFORMANCE-POSTS-LATEST-BY-ID-LATEST.md`
- Benchmark `posts.latest_by_id` Gate/Ampel: `docs/PERFORMANCE-POSTS-LATEST-BY-ID-GATE-LATEST.md`
- Benchmark `posts.latest_by_id` Staging/Prod: `docs/PERFORMANCE-POSTS-LATEST-BY-ID-STAGING-PROD.md`
- Smoke-Report lokal (Referenzlauf): `docs/SMOKE-PASS-2026-03-09.md`
- Smoke-Report Staging/Prod: `docs/SMOKE-PASS-STAGING-PROD.md`
- Architekturentscheidungen (ADR): `docs/adr/`
- Plesk Deployment: `docs/PLESK_DEPLOYMENT_FUER_ANFAENGER.md`
- GitHub + Plesk Setup: `docs/GITHUB_PLESK_SETUP.md`

## 1) Executive Summary
- Produktstatus: **Release-Beta (stabilisiert, Multi-Welt-faehig)**.
- Plattformname: **C76-RPG**.
- Laufende Versionslinie: **`v0.25-beta`**.
- Verifikation lokal (letzter Lauf):
  - `php artisan test --without-tty --do-not-cache-result` -> **243 passed, 1227 assertions** (2026-03-30)
  - `php artisan test tests/Unit/Domain/ServiceScopeInvariantTest.php tests/Feature/CampaignScenePostWorkflowTest.php tests/Unit/Actions/Character/CreateCharacterActionTest.php tests/Unit/ProbeRollerTest.php` -> **29 passed, 203 assertions** (2026-03-23)
  - `node --test tests/js/*.mjs` -> **18 passed** (2026-03-30)
  - `composer analyse` -> **keine Fehler (PHPStan Level 8)** (2026-03-30)
  - `npm run build` -> **gruen** (2026-03-19)
- Delivery-Basis steht:
  - CI Workflow aktiv (`.github/workflows/ci.yml`)
  - Release-Smoke-Skript aktiv (`scripts/release_smoke.sh`, inkl. Weltkontext-/Routing-Checks)

## 2) Produktstatus nach Bereichen

| Bereich | Status | Bemerkung |
|---|---|---|
| Auth (Register/Login/Reset) | Stabil | Rollen- und Session-Flows produktiv nutzbar |
| Charaktere + Charakterbogen | Stabil | CRUD, Ownership, LE/AE, Inventar/Waffen/Ruestung |
| Kampagnen/Szenen/Posts | Stabil | IC/OOC, Moderation, Revisionen, Pinning |
| GM-Proben + Persistenz | Stabil | d100, Zielwert/Modifikator, LE/AE-Impact, RS-Minderung |
| Szenen-Abos / Read-Tracking / Jump-Links | Stabil | Unread-Logik und schnelle Navigation |
| Kampagnen-Einladungen | Stabil | Rollenfluss inkl. Co-GM |
| Wissenszentrum / Enzyklopaedie | Stabil | Oeffentliche Seiten + GM/Admin-Redaktion |
| Browser-Benachrichtigungen | Aktiv | Echte Web Push Zustellung (VAPID) + Service-Worker Click |
| PWA-Basis | Stabil | Manifest, Offline-Lesen, Offline-Post-Queue inkl. 419-Re-Signing + Retry-Backoff |
| Domänen-Invarianten + Retry-Resilienz | Stabil | Harte Service-Guards (Welt/Teilnahme), Invariant-Exceptions, Queue-Retry fuer Notification-Fehler |
| Recht / Compliance | Aktiv | Zentrale Links auf c76.org, Footer vereinheitlicht |

## 3) Multi-Welt-Umstellung (neu)

### 3.1 Datenmodell
- Neue Domaene: `worlds` (`app/Models/World.php`)
- Weltbindung als Pflicht-FK in:
  - `campaigns.world_id`
  - `characters.world_id`
  - `encyclopedia_categories.world_id`
- Backfill bestehender Daten auf `chroniken-der-asche`.
- Slug-Unique bei Kategorien auf `(world_id, slug)` umgestellt.

### 3.2 Routing und Konsistenz
- Canonical Routen unter `/w/{world}/...`.
- Legacy-Routen ohne Weltsegment liefern `301` auf Welt-URL.
- Controller/Policies pruefen Weltkonsistenz (Mismatches -> `404`).
- Kampagnen-Erstellung erfolgt im Weltkontext.

### 3.3 Admin und UX
- Admin-CRUD fuer Welten vorhanden.
- Landingpage auf generisches `C76-RPG` umgestellt.
- Weltkatalog als Einstieg vorhanden (`/welten`).
- Wissensbereiche und Enzyklopaedie sind weltgetrennt.

## 4) Architektur- und Code-Status
- Controller sind auf Orchestrierung reduziert.
- Fachlogik in Domain Services:
  - `app/Domain/Post/*`
  - `app/Domain/Scene/*`
  - `app/Domain/Campaign/CampaignParticipantResolver.php`
- Harte Invarianten fuer Probe-/Inventar-Flows:
  - Service-seitig durchgesetzt ueber domänenspezifische Exceptions (`PostProbeInvariantViolationException`, `PostInventoryAwardInvariantViolationException`, `SceneInventoryQuickActionInvariantViolationException`)
  - Controller mappen Invariant-Fehler in validierungsnahe User-Fehlermeldungen (statt 500)
- Benachrichtigungs-Resilienz:
  - `PostNotificationOrchestrator` mit sofortigem Versuch + Queue-Retry-Fallback (`RetryScenePostNotificationsJob`, `RetryPostMentionNotificationsJob`)
- Character-Create-Flow ist in Action/Services aufgeteilt:
  - `app/Actions/Character/CreateCharacterAction.php` (Transaktion, Inventory-Audit, after-commit Avatar-Finalisierung)
  - `app/Services/Character/AttributeNormalizer.php` (Backfill + Sanitizing + Pool-Normalisierung)
  - `app/Services/Character/AvatarService.php` (Stage/Finalize/Cleanup)
  - `app/Exceptions/CharacterCreationFailedException.php` (expliziter Fehlerpfad)
  - `CharacterController::store()` delegiert nur noch an die Action
- Post-Update-Flow ist in Action ausgelagert:
  - `app/Actions/Post/UpdatePostAction.php` (Moderationsentscheidung, Revisionssnapshot, Mention-Dispatch)
  - `PostController::update()` delegiert auf die Action
  - Unit-Absicherung: `tests/Unit/Actions/Post/UpdatePostActionTest.php`
- Scene-ThreadPage-Flow ist teilweise entkoppelt:
  - `app/Actions/Scene/BuildSceneThreadPageDataAction.php` (Paginator, Subscription-Lookup, Unread-Berechnung, Moderationsflag)
  - `app/Actions/Scene/SceneThreadPageData.php` (typsicheres Ergebnisobjekt fuer das Thread-Fragment)
  - `SceneController::threadPage()` delegiert auf die Action
  - Unit-Absicherung: `tests/Unit/Actions/Scene/BuildSceneThreadPageDataActionTest.php`
- Character-Update-Flow ist teilweise entkoppelt:
  - `app/Actions/Character/UpdateCharacterAction.php` (Transaktion, Inventory-Diff-Logging, Avatar-Stage/Finalize/Cleanup)
  - `CharacterController::update()` delegiert auf die Action
  - Unit-Absicherung: `tests/Unit/Actions/Character/UpdateCharacterActionTest.php`
- Architekturentscheidung dokumentiert in:
  - `docs/adr/2026-03-08-post-scene-domain-services.md`

## 5) Delivery, Betrieb und Compliance

### 5.1 CI / Release
- CI Gates:
  - `composer validate --strict`
  - `composer analyse`
  - `php artisan test --without-tty --do-not-cache-result`
  - `npm run build`
- Wichtiger Test-Hinweis:
  - vor lokalen Feature-Testlaeufen nach aktivierten Caches immer `php artisan optimize:clear`
- Release-Checkliste:
  - `docs/RELEASE-CHECKLISTE.md`
- Release-Prepare (Version/Build/Doku):
  - `scripts/release_prepare.sh --version vX.XX-beta --build "$(git rev-parse --short HEAD)"`
- Release-Flow (fixe Reihenfolge inkl. Prepare):
  - `scripts/release_flow.sh --version vX.XX-beta`
- Smoke-Report-Ausgabe:
  - `SMOKE_REPORT_OUT=docs/SMOKE-PASS-STAGING-PROD.md scripts/release_smoke.sh`
  - `WORLD_DEFAULT_SLUG` wird in den Release-/Smoke-Skripten bei leerem Shell-Env direkt aus `.env` gelesen
  - Bei externer `SMOKE_BASE_URL` startet `release_smoke.sh` keinen lokalen `artisan serve`
- DB-Betriebsmodus:
  - Produktion: MySQL/MariaDB
  - CI-Tests: SQLite in-memory (`phpunit.xml`)
  - WebPush-DB folgt standardmaessig `DB_CONNECTION` (Override nur bei Bedarf via `WEBPUSH_DB_CONNECTION`)

### 5.2 Observability
- Request-Korrelation aktiv (`X-Request-Id`).
- Strukturierte Logs via `app/Support/Observability/StructuredLogger.php`.
- Incident-Ablauf im Runbook dokumentiert.
- Web Push:
  - Zustellung und Subscription-Lifecycle mit strukturierten Events:
    - `webpush.subscription_upserted`
    - `webpush.subscription_deleted`
    - `webpush.scene_post_sent`
    - `webpush.delivery_failed`
- Hotpath-Performance initial dokumentiert:
  - `docs/PERFORMANCE-PASS-2026-03-09.md`
  - Reproduzierbarer EXPLAIN-Runner: `php artisan perf:world-hotpaths`
  - Staging/Prod-Lauf protokolliert: `docs/PERFORMANCE-PASS-STAGING-PROD.md`
  - `scene_subscriptions.unread_count` auf `EXISTS` umgestellt und auf Prod erfolgreich validiert
- `posts.latest_by_id` Benchmark-Runner vorhanden:
  - `scripts/perf_posts_latest_by_id.sh` (inkl. Delta-Report)
  - `scripts/release_perf_gate.sh` (inkl. Ampel-Entscheidung fuer Release)
  - Optionaler Runtime-Hint via ENV (`PERF_POSTS_LATEST_BY_ID_FORCE_INDEX=true`, MySQL/MariaDB)
  - `php artisan perf:posts-latest-by-id-benchmark --world=chroniken-der-asche --iterations=400 --out=docs/PERFORMANCE-POSTS-LATEST-BY-ID-STAGING-PROD.md` (Fallback/Raw)
  - Automatischer Vergleichsreport: `docs/PERFORMANCE-POSTS-LATEST-BY-ID-LATEST.md`
  - Automatischer Gate-Report: `docs/PERFORMANCE-POSTS-LATEST-BY-ID-GATE-LATEST.md`
  - Baseline im aktuellen Delta-Report: `docs/PERFORMANCE-POSTS-LATEST-BY-ID-2026-03-17.md`
  - Letzter datierter Lauf: `docs/PERFORMANCE-POSTS-LATEST-BY-ID-2026-03-20.md`
  - Prod-Benchmark dokumentiert: `docs/PERFORMANCE-POSTS-LATEST-BY-ID-STAGING-PROD.md`
  - Ergebnis Prod: `FORCE INDEX posts_scene_id_id_idx` im Sample schneller als Default (avg/p95), beide Pfade aber bereits im Sub-Millisekundenbereich

### 5.3 Rechtliches / Lizenz
- Rechtstexte zentral auf Hauptdomain:
  - `https://c76.org/impressum/`
  - `https://c76.org/datenschutz/`
- Footer-Links auf allen sichtbaren Seiten.
- Repo-Lizenz: `LICENSE` (proprietaer / all rights reserved).

### 5.4 Frontend-Abhaengigkeiten
- HTMX 2.x + Alpine.js 3.x lokal via Vite (keine Runtime-CDN-Abhaengigkeit).
- Keine zusaetzlichen SPA-Frameworks (kein Livewire/Inertia/Vue/React).

## 6) Offene Risiken und Restthemen
- Kein WebSocket-/Realtime-Backbone (bewusste Entscheidung fuer asynchrones PbP).
- Kein externes Media/CDN-Setup.

## 7) Empfohlene naechste Schritte
1. `scripts/release_flow.sh --version ...` als Standard vor jedem Release nutzen.
2. Perf-Gate-Statushistorie (`...GATE-LATEST.md`) bei jeder Staging/Prod-Runde fortschreiben.
3. Runtime-Hint aktiv lassen oder deaktivieren anhand wiederholter Messungen im Zielsystem.

---
Diese Datei ist der operative Master-Status fuer Produkt, Technik und Delivery.
