# Chroniken der Asche - Projekt-Uebersicht

Stand: 2026-03-08  
Repository-Referenz: `f61ea18` (main)

## Quicklinks
- Einstieg und Setup: `README.md`
- 6-Monats-Plan: `ROADMAP.md`
- Release-Ablauf: `docs/RELEASE-CHECKLISTE.md`
- Betrieb/Incident-Handling: `docs/OPERATIONS_RUNBOOK.md`
- Architekturentscheidungen (ADR): `docs/adr/`
- Plesk Deployment: `docs/PLESK_DEPLOYMENT_FUER_ANFAENGER.md`
- GitHub + Plesk Setup: `docs/GITHUB_PLESK_SETUP.md`

## 1) Executive Summary
- Produktstatus: **Release-Beta in stabilisiertem Zustand**.
- Roadmap-Status: **12/12 Sprints abgeschlossen** (siehe `ROADMAP.md`).
- Laufende Versionslinie: **`v0.17-beta`**.
- Aktuelle Qualitaetslage (lokal verifiziert auf `f61ea18`):
  - `php artisan test --without-tty --do-not-cache-result` -> **131 passed, 665 assertions**
  - `npm run build` -> **gruen**
- Delivery-Basis ist etabliert:
  - CI Workflow aktiv (`.github/workflows/ci.yml`)
  - Release-Smoke-Skript aktiv (`scripts/release_smoke.sh`)
- Compliance-Basis ist umgesetzt:
  - Rechtliche Seiten (`/impressum`, `/datenschutz`, `/copyright`)
  - Footer-Links auf allen sichtbaren Seiten
  - Repo-Lizenz klar als proprietär (`LICENSE`, Composer-Metadaten)

## 2) Produktstatus nach Domainen

| Bereich | Status | Bemerkung |
|---|---|---|
| Auth (Register/Login/Reset) | Stabil | Rollen- und Session-Flows produktiv nutzbar |
| Charaktere + Charakterbogen | Stabil | CRUD, Ownership, LE/AE, Inventar/Waffen/Ruestung |
| Kampagnen/Szenen/Posts | Stabil | IC/OOC, Moderation, Revisionen, Pinning |
| GM-Proben + Persistenz | Stabil | d100, Zielwert/Modifikator, LE/AE-Impact, RS-Minderung |
| GM-Inventar-Operationen | Stabil | Post-Award + Szenen-Quick-Action + Audit-Log |
| Benachrichtigungen (Inbox/Mail) | Stabil | Praeferenzgesteuert pro Kanal |
| Browser-Benachrichtigungen | Aktiv | Permission + Polling + Service-Worker Notification Click |
| Rechtliche Seiten | Aktiv | Impressum, Datenschutz und Copyright-Seite integriert |
| Szenen-Abos / Read-Tracking / Jump-Links | Stabil | Unread-Logik und schnelle Navigation vorhanden |
| Kampagnen-Einladungen | Stabil | Rollenfluss inkl. Co-GM |
| Wissenszentrum / Enzyklopaedie | Stabil | Oeffentliche Seiten + GM/Admin-Redaktion |
| PWA Basis | Stabil | Manifest, Offline-Lesen, Offline-Post-Queue |

Hinweis zu "Push":
- Browser-Benachrichtigungen sind implementiert als **permission-gesteuertes Polling** auf `/notifications/poll`.
- Vollstaendiger Web-Push-Stack (z. B. VAPID/Subscription-Management) ist weiterhin optional und nicht erforderlich fuer den aktuellen Release-Beta-Stand.

## 3) Architektur- und Code-Status

### 3.1 Anwendungsarchitektur
- Controller sind auf Orchestrierung reduziert.
- Fachlogik wurde in Domain Services extrahiert:
  - `app/Domain/Post/*`
  - `app/Domain/Scene/*`
  - `app/Domain/Campaign/CampaignParticipantResolver.php`
- Architekturentscheidung dokumentiert in:
  - `docs/adr/2026-03-08-post-scene-domain-services.md`

### 3.2 Benachrichtigungsarchitektur (aktueller Stand)
- Interne Notification-Kanaele:
  - `database`
  - `mail`
  - `browser` (als Praeferenzkanal)
- Poll-Endpunkt:
  - `GET /notifications/poll` (auth + `throttle:notifications`)
- Frontend-Flow:
  - Permission-Anfrage ueber UI
  - Polling im Frontend
  - Dedupe bereits gezeigter IDs via `localStorage`
  - Anzeige per Service Worker `showNotification` (Fallback: `new Notification()`)
- Service Worker:
  - `notificationclick` fuehrt auf `action_url` und fokussiert vorhandene Tabs
- Frontend-Auslieferung:
  - Alpine wird lokal gehostet (`public/js/alpinejs-3.14.8.min.js`)
  - keine Laufzeit-Abhängigkeit zu externem Script-CDN für Alpine

### 3.3 Datenbank / Performance
- Hot-Path-Indizes sind vorhanden:
  - Migration: `database/migrations/2026_03_08_120000_add_hot_path_indexes_for_post_scene_and_invitation_queries.php`
- Kritische Kernpfade (`posts`, `scene_subscriptions`, `campaign_invitations`) wurden auf Query-Last optimiert.

### 3.4 Observability
- Request-Korrelation aktiv:
  - Middleware: `app/Http/Middleware/AttachRequestId.php`
  - Response Header: `X-Request-Id`
- Strukturierte Events vorhanden via:
  - `app/Support/Observability/StructuredLogger.php`
- Incident-Ablauf und Suchschluessel sind im Runbook dokumentiert.

## 4) Delivery, Release und Betrieb

### 4.1 CI (verbindlich)
- Workflow: `.github/workflows/ci.yml`
- Gates:
  - `composer validate --strict`
  - `php artisan test --without-tty --do-not-cache-result`
  - `npm run build`

### 4.2 Release-Schnittstellen
- Release-Checkliste:
  - `docs/RELEASE-CHECKLISTE.md`
- Smoke-Automation:
  - `scripts/release_smoke.sh`
- Plesk Post-Deploy:
  - `scripts/plesk_post_deploy.sh`
- Rechtliche Mindestsichtbarkeit:
  - Footer enthält auf allen sichtbaren Seiten Links zu Impressum und Datenschutz.

### 4.3 Versionierung
- Sichtbare Version ueber `APP_VERSION`.
- Build-Kennung ueber `APP_BUILD`.
- Nach Aenderung auf Zielsystem:
  - `php artisan optimize:clear`
  - `php artisan config:cache`

## 5) Qualitaetsstatus und Testabdeckung
- Test-Suite ist gruener Referenzstand auf `f61ea18`:
  - **131 Tests passed**
  - **665 Assertions**
- Notification-Erweiterung ist testseitig abgedeckt durch:
  - Praeferenz-Tests (inkl. Browser-Kanal-Regeln)
  - Poll-Endpunkt-Tests (`tests/Feature/NotificationPollTest.php`)
  - bestehende Workflow- und Subscription-Tests
- Rechtliche Seiten sind testseitig abgedeckt durch:
  - `tests/Feature/LegalPagesTest.php`

## 6) Offene Risiken und technische Restthemen
- Kein Realtime/WebSocket-Backbone (bewusste Produktentscheidung fuer asynchrones PbP).
- Browser-Benachrichtigungen basieren auf Polling; ein spaeterer Wechsel auf echten Web-Push ist optionales Ausbau-Thema.
- Keine externe Media/CDN-Optimierung (fuer aktuellen Beta-Rahmen akzeptabel).

## 7) Empfohlene naechste Schritte (nach Sprint-12 Abschluss)
1. Stabilitaetsphase fortsetzen (Bugburn, kleine UX-Korrekturen, keine neuen grossen Features).
2. Release-Takt absichern: zwei weitere stoerungsfreie Deploy-Zyklen strikt nach Checkliste.
3. Optionales Architekturthema vorbereiten: Web-Push als separates, klar begrenztes Folgeprojekt.
4. Doku-Disziplin beibehalten: nach jedem Release mindestens `README`, `ROADMAP`, `docs/PROJEKT-ÜBERSICHT.md`, `docs/RELEASE-CHECKLISTE.md` abgleichen.

## 8) Letzte relevante Commits
- `f61ea18` - Proprietäre Lizenzdatei ergänzt und Composer-Lizenzmetadaten ausgerichtet.
- `6237c3d` - Footer-Rechtsstruktur auf Impressum/Datenschutz + Rights-Notice umgestellt.
- `15f6b22` - Rechtliche Seiten ergänzt und Alpine lokal gehostet (CDN entfernt).
- `0940cb8` - Browser notification polling + permission flow + Poll-Tests.
- `f00cfd6` - Sprint-12/Roadmap-Dokumentation auf abgeschlossen gesetzt.

---
Diese Datei ist der operative Master-Status fuer Produkt, Technik und Delivery.
