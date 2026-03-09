# C76-RPG - Projekt-Uebersicht

Stand: 2026-03-09  
Repository-Branch: `main`

## Quicklinks
- Einstieg und Setup: `README.md`
- Gesamt-Roadmap: `ROADMAP.md`
- Release-Ablauf: `docs/RELEASE-CHECKLISTE.md`
- Betrieb/Incident-Handling: `docs/OPERATIONS_RUNBOOK.md`
- Performance-Pass: `docs/PERFORMANCE-PASS-2026-03-09.md`
- Performance Staging/Prod: `docs/PERFORMANCE-PASS-STAGING-PROD.md`
- Smoke-Report lokal: `docs/SMOKE-PASS-2026-03-09.md`
- Architekturentscheidungen (ADR): `docs/adr/`
- Plesk Deployment: `docs/PLESK_DEPLOYMENT_FUER_ANFAENGER.md`
- GitHub + Plesk Setup: `docs/GITHUB_PLESK_SETUP.md`

## 1) Executive Summary
- Produktstatus: **Release-Beta (stabilisiert, Multi-Welt-faehig)**.
- Plattformname: **C76-RPG**.
- Laufende Versionslinie: **`v0.19-beta`**.
- Verifikation lokal (letzter Lauf):
  - `php artisan test --without-tty --do-not-cache-result` -> **138 passed, 686 assertions**
  - `npm run build` -> **gruen**
- Delivery-Basis steht:
  - CI Workflow aktiv (`.github/workflows/ci.yml`)
  - Release-Smoke-Skript aktiv (`scripts/release_smoke.sh`, inkl. Weltkontext-/Legacy-Redirect-Checks)

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
| Browser-Benachrichtigungen | Aktiv | Permission + Polling + Service-Worker Click |
| PWA-Basis | Stabil | Manifest, Offline-Lesen, Offline-Post-Queue |
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
- Architekturentscheidung dokumentiert in:
  - `docs/adr/2026-03-08-post-scene-domain-services.md`

## 5) Delivery, Betrieb und Compliance

### 5.1 CI / Release
- CI Gates:
  - `composer validate --strict`
  - `composer analyse`
  - `php artisan test --without-tty --do-not-cache-result`
  - `npm run build`
- Release-Checkliste:
  - `docs/RELEASE-CHECKLISTE.md`
- Smoke-Report-Ausgabe:
  - `SMOKE_REPORT_OUT=docs/SMOKE-PASS-STAGING-PROD.md scripts/release_smoke.sh`

### 5.2 Observability
- Request-Korrelation aktiv (`X-Request-Id`).
- Strukturierte Logs via `app/Support/Observability/StructuredLogger.php`.
- Incident-Ablauf im Runbook dokumentiert.
- Hotpath-Performance initial dokumentiert:
  - `docs/PERFORMANCE-PASS-2026-03-09.md`
  - Reproduzierbarer EXPLAIN-Runner: `php artisan perf:world-hotpaths`
  - Staging/Prod-Lauf protokolliert: `docs/PERFORMANCE-PASS-STAGING-PROD.md`
  - `scene_subscriptions.unread_count` auf `EXISTS` umgestellt und auf Prod erfolgreich validiert

### 5.3 Rechtliches / Lizenz
- Rechtstexte zentral auf Hauptdomain:
  - `https://c76.org/impressum/`
  - `https://c76.org/datenschutz/`
- Footer-Links auf allen sichtbaren Seiten.
- Repo-Lizenz: `LICENSE` (proprietaer / all rights reserved).

### 5.4 Frontend-Abhaengigkeiten
- Alpine lokal gehostet (keine Runtime-CDN-Abhaengigkeit).

## 6) Offene Risiken und Restthemen
- Kein WebSocket-/Realtime-Backbone (bewusste Entscheidung fuer asynchrones PbP).
- Browser-Benachrichtigungen aktuell pollingbasiert (kein VAPID/Web-Push).
- Kein externes Media/CDN-Setup.

## 7) Empfohlene naechste Schritte
1. Staging/Prod-Smoke pro Deploy als Report aktualisieren (`docs/SMOKE-PASS-STAGING-PROD.md`).
2. Optional: `posts.latest_by_id`-Plan bei steigender Datenmenge benchmarken.
3. Doku bei jedem Release synchron halten (`README`, `ROADMAP`, Projekt-Uebersicht, Release-Checkliste).

---
Diese Datei ist der operative Master-Status fuer Produkt, Technik und Delivery.
