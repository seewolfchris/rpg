# C76-RPG

C76-RPG ist eine deutschsprachige, privacy-first Play-by-Post-Plattform für story-fokussierte Multi-World-Kampagnen.
Der Fokus liegt auf asynchronem Storytelling, klaren Weltkontexten und einem schlanken Laravel-/Blade-/HTMX-Stack statt auf SPA-Overhead.

## Zielgruppe

### Geeignet für
- Betreiber kleiner bis mittlerer story-orientierter PbP-Communities
- Teams, die Laravel + Blade + HTMX für asynchrone Produktoberflächen nutzen
- Projekte, die Moderation, Rollenmodell und Privacy-Boundary priorisieren

### Nicht geeignet für
- Echtzeit-Chat-RPG mit WebSocket-First-Architektur
- Generische Forum-Software-Ersatzfälle ohne PbP-Fokus
- SPA-First-Stacks (z. B. Vue/React/Inertia als Kernvoraussetzung)

## Warum C76-RPG?

- Asynchrones, romanartiges Storytelling statt Realtime-Druck
- Mehrweltfähiges Kampagnenmodell mit klaren Weltkontexten
- Moderierte Posting-Workflows für private und öffentliche Kampagnen
- Privacy-Boundary für Auth-Wechsel, Logout und Offline-Daten
- Schlanker Blade/HTMX/Alpine-Stack mit wenig Laufzeitkomplexität
- Kein generisches Forum und keine SPA-Pflicht, sondern serverseitig robuste PbP-Flows

## Kernfeatures

- Authentifizierung mit Rollen (`player`, `gm`, `admin`, kampagnenspezifisch `co_gm`)
- Kampagnen-/Szenenverwaltung mit Sichtbarkeit, Einladungen, Read-Tracking und Bookmarks
- Privacy-first SL-Kontakt pro Kampagne (Thread-basiert, kein Chat/Realtime, kein Dashboard-Flow)
- Posting mit IC/OOC, Spoiler, Edit-Historie und Moderationspfad
- Charakterverwaltung inkl. Ownership-/Policy-Checks
- Benachrichtigungen (In-App, Mail, Browser Web Push)
- PWA-Basis mit Offline-Lesen für definierte Seiten und Offline-Post-Queue
- Multi-World-Routing unter `/w/{world}/...` inkl. Legacy-Redirects

## Technischer Rahmen

| Bereich | Stand |
| --- | --- |
| Runtime | PHP 8.5+, Laravel 12 |
| Frontend | Blade, HTMX 2.x, Alpine.js 3.x, Tailwind (Vite) |
| Datenbank | MySQL/MariaDB empfohlen (Produktion) |
| Queue/Cache (Produktion) | Redis empfohlen |
| SQLite | nur für bestimmte lokale/CI-Pfade |

Bewusst kein Livewire, Inertia oder Vue/React als Grundvoraussetzung.

## Quick Start (Lokal)

### Voraussetzungen
- `php -v` (>= 8.5)
- `composer -V`
- `node -v` und `npm -v`

### Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

### Start

Terminal 1:

```bash
php artisan serve
```

Terminal 2:

```bash
npm run dev
```

App aufrufen: `http://127.0.0.1:8000`

Für produktionsnahe lokale Entwicklung MySQL/MariaDB statt SQLite nutzen.

## Qualität (Kurz)

Schneller Standardlauf für lokale Verifikation:

```bash
php artisan optimize:clear
php artisan test
npm run test:js
npm run build
```

Erweiterte Gates (Analyse, E2E, Release-Pipeline): siehe [docs/RELEASE-CHECKLISTE.md](docs/RELEASE-CHECKLISTE.md).

## Produktion (Kurz)

- Produktionsziel: MySQL/MariaDB + Redis
- SQLite ist kein vorgesehener Produktionspfad
- Webroot muss auf `public/` zeigen
- Deploy-Basis: `php artisan migrate --force` und `npm run build`
- Queue-Worker in Produktion aktiv betreiben
- Vollständige Betriebs-/Deploy-Schritte stehen in [docs/OPERATIONS_RUNBOOK.md](docs/OPERATIONS_RUNBOOK.md) und [docs/PLESK_DEPLOYMENT_FUER_ANFAENGER.md](docs/PLESK_DEPLOYMENT_FUER_ANFAENGER.md)

## Aktueller Status

- Status: Beta (`v0.30-beta`), aktiv entwickelt
- Kernbereiche (Authentifizierung, Kampagnen/Szenen, Posting/Moderation, PWA-Boundary): stabil
- Test-/Analyse-Gates: `php artisan test`, `composer analyse`, `npm run test:js`, `npm run test:e2e`, `npm run build`
- Historie und Release-Notizen: [CHANGELOG.md](CHANGELOG.md)

## Bekannte Grenzen / Nicht-Ziele

- Kein Realtime-WebSocket-Produktkern (HTTP-first für asynchrones PbP)
- Web Push nur mit Browser-Permission und aktivem Service Worker
- Keine externe Medien-CDN-Optimierung als Standardpfad

## Dokumentation

- Roadmap: [ROADMAP.md](ROADMAP.md)
- Release-Flow und Quality-Gates: [docs/RELEASE-CHECKLISTE.md](docs/RELEASE-CHECKLISTE.md)
- Betrieb/Incidents: [docs/OPERATIONS_RUNBOOK.md](docs/OPERATIONS_RUNBOOK.md)
- Deployment (Plesk): [docs/PLESK_DEPLOYMENT_FUER_ANFAENGER.md](docs/PLESK_DEPLOYMENT_FUER_ANFAENGER.md)
- GitHub->Plesk Setup: [docs/GITHUB_PLESK_SETUP.md](docs/GITHUB_PLESK_SETUP.md)
- PWA/Offline-Details: [docs/PWA_OFFLINE.md](docs/PWA_OFFLINE.md)
- Security-Hardening (technisch): [docs/SECURITY.md](docs/SECURITY.md)
- Security-Disclosure: [SECURITY.md](SECURITY.md)
- Architektur-Entscheidungen: [docs/adr](docs/adr)

## Lizenz / Security / Beiträge

- Lizenz: proprietär, siehe [LICENSE](LICENSE)
- Nutzung/Weitergabe außerhalb der vereinbarten Rahmenbedingungen ist nicht frei
- Security-Meldungen: siehe [SECURITY.md](SECURITY.md)
- Beiträge: nur nach Absprache, siehe [CONTRIBUTING.md](CONTRIBUTING.md)
