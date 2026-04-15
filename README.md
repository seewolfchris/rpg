# C76-RPG

C76-RPG ist eine deutschsprachige, privacy-first Play-by-Post-Plattform fuer story-fokussierte Multi-World-Kampagnen mit moderierter Posting-Logik, PWA-Unterbau und bewusst ohne SPA-Overhead.
Die Startwelt `Chroniken der Asche` bleibt erhalten und ist Teil eines mehrweltfaehigen Produktmodells.

## Fuer Wen / Fuer Wen Nicht

### Fuer wen
- Betreiber kleiner bis mittlerer story-orientierter PbP-Communities
- Teams, die Laravel + Blade + HTMX fuer asynchrone Produktoberflaechen nutzen
- Projekte, die Moderation, Rollenmodell und Privacy-Boundary priorisieren

### Fuer wen nicht
- Echtzeit-Chat-RPG mit WebSocket-First-Architektur
- Generische Forum-Software-Ersatzfaelle ohne PbP-Fokus
- SPA-First-Stacks (z. B. Vue/React/Inertia als Kernvoraussetzung)

## Warum C76-RPG?

- Asynchrones, romanartiges Storytelling statt Realtime-Druck
- Mehrweltfaehiges Kampagnenmodell mit klaren Weltkontexten
- Moderierte Posting-Workflows fuer private und oeffentliche Kampagnen
- Privacy-Boundary fuer Auth-Wechsel, Logout und Offline-Daten
- Schlanker Blade/HTMX/Alpine-Stack mit wenig Laufzeitkomplexitaet

## Kernfeatures

- Authentifizierung mit Rollen (`player`, `gm`, `admin`, kampagnenspezifisch `co_gm`)
- Kampagnen-/Szenenverwaltung mit Sichtbarkeit, Einladungen, Read-Tracking und Bookmarks
- Posting mit IC/OOC, Spoiler, Edit-Historie und Moderationspfad
- Charakterverwaltung inkl. Ownership-/Policy-Checks
- Benachrichtigungen (In-App, Mail, Browser Web Push)
- PWA-Basis mit Offline-Lesen fuer definierte Seiten und Offline-Post-Queue
- Multi-World-Routing unter `/w/{world}/...` inkl. Legacy-Redirects

## Stack / Support-Matrix

| Bereich | Stand |
| --- | --- |
| Runtime | PHP 8.5+, Laravel 12 |
| Frontend | Blade, HTMX 2.x, Alpine.js 3.x, Tailwind (Vite) |
| Datenbank | MySQL/MariaDB empfohlen (Produktion) |
| Queue/Cache (Produktion) | Redis empfohlen |
| SQLite | nur fuer bestimmte lokale/CI-Pfade |
| Architekturprinzip | kein Livewire, kein Inertia, kein Vue/React-Zwang |

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

## Qualitaet (Kurz)

```bash
php artisan optimize:clear
php artisan test
npm run test:js
npm run build
```

Erweiterte Gates (Analyse, E2E, Release-Pipeline): siehe [docs/RELEASE-CHECKLISTE.md](docs/RELEASE-CHECKLISTE.md).

## Produktion (Kurz)

- Produktionsziel: MySQL/MariaDB + Redis
- Webroot muss auf `public/` zeigen
- Deploy-Basis: `php artisan migrate --force` und `npm run build`
- Queue-Worker in Produktion aktiv betreiben
- Vollstaendige Betriebs-/Deploy-Schritte stehen in [docs/OPERATIONS_RUNBOOK.md](docs/OPERATIONS_RUNBOOK.md) und [docs/PLESK_DEPLOYMENT_FUER_ANFAENGER.md](docs/PLESK_DEPLOYMENT_FUER_ANFAENGER.md)

## Aktueller Status

- Status: Beta (`v0.29-beta`), aktiv entwickelt
- Kernfunktionen: stabil im laufenden Ausbau
- Test-/Analyse-Gates: `php artisan test`, `composer analyse`, `npm run test:js`, `npm run test:e2e`, `npm run build`
- Historie und Release-Notizen: [CHANGELOG.md](CHANGELOG.md)

## Bekannte Grenzen / Nicht-Ziele

- Kein Realtime-WebSocket-Produktkern (HTTP-first fuer asynchrones PbP)
- Web Push nur mit Browser-Permission und aktivem Service Worker
- Keine externe Medien-CDN-Optimierung als Standardpfad

## Dokumentation

- Release-Flow und Quality-Gates: [docs/RELEASE-CHECKLISTE.md](docs/RELEASE-CHECKLISTE.md)
- Betrieb/Incidents: [docs/OPERATIONS_RUNBOOK.md](docs/OPERATIONS_RUNBOOK.md)
- PWA/Offline-Details: [docs/PWA_OFFLINE.md](docs/PWA_OFFLINE.md)
- Security-Hardening (technisch): [docs/SECURITY.md](docs/SECURITY.md)
- Security-Disclosure: [SECURITY.md](SECURITY.md)
- Deployment (Plesk): [docs/PLESK_DEPLOYMENT_FUER_ANFAENGER.md](docs/PLESK_DEPLOYMENT_FUER_ANFAENGER.md)
- GitHub->Plesk Setup: [docs/GITHUB_PLESK_SETUP.md](docs/GITHUB_PLESK_SETUP.md)
- Architektur-Entscheidungen: [docs/adr](docs/adr)
- Roadmap: [ROADMAP.md](ROADMAP.md)

## Lizenz / Security / Contributions

- Lizenz: proprietaer, siehe [LICENSE](LICENSE)
- Nutzung/Weitergabe ausserhalb der vereinbarten Rahmenbedingungen ist nicht frei
- Security-Meldungen: siehe [SECURITY.md](SECURITY.md)
- Contributions: nur nach Absprache, siehe [CONTRIBUTING.md](CONTRIBUTING.md)
