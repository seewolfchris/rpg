# Chroniken der Asche (Laravel Beta)

Dark-Fantasy Play-by-Post (PbP) RPG auf Laravel mit lokalem Font-Setup, asynchronen Kampagnen/Szenen, Post-Moderation, GM-Proben, Benachrichtigungen, Gamification und PWA-Basis.

## Dokumentations-Uebersicht

- Projekt-Quickstart und Betrieb: `README.md`
- Master-Handbuch (fachliche Gesamtuebersicht): `docs/PROJEKT-ÜBERSICHT.md`
- Release-Ablauf (Version + Deploy): `docs/RELEASE-CHECKLISTE.md`
- Plesk Deployment fuer Einsteiger: `docs/PLESK_DEPLOYMENT_FUER_ANFAENGER.md`
- GitHub + Plesk Setup: `docs/GITHUB_PLESK_SETUP.md`

## Beta-Status

Stand: **Beta 1** (funktional, getestet, build-fähig)

Enthalten:
- Auth: Registrierung/Login/Logout (Breeze-Style, Blade)
- Charaktersystem: CRUD, Eigenschaften, Biografie, Avatar-Upload, Ownership-Checks
- Kampagnen/Szenen: Erstellung, Sichtbarkeit, Filter, Rollen (Owner/Co-GM/Player)
- Posts: IC/OOC, Markdown/BBCode/Plain, Spoiler, Edit-History (Revisionen)
- Moderation: Ausstehend/Freigegeben/Abgelehnt, Audit-Log, GM-Queue, Bulk-Aktionen
- Pinning: Wichtige Posts in Szenen anpinnen/entpinnen
- Einladungen: Ausstehend/Annehmen/Ablehnen inkl. Rollen (Player/Co-GM)
- Read-Tracking: Ungelesen-Status, Read/Unread-Aktionen, Jump-Links
- Bookmarks: Szenen-Bookmark je User inkl. Jump auf Post/Page
- GM-Proben im Post: GM-only mit Anlass/Ziel-Held/Modifikator, d100-Rollmode, Log in DB und direkter LE/AE-Persistenz
- Benachrichtigungen: In-App + Mail-Kanäle (präferenzgesteuert)
- Gamification: Punkte für freigegebene Posts
- Wissenszentrum: Uebersicht, Wie-spielt-man, Regelwerk, Enzyklopaedie
- Enzyklopaedie-Admin: Kategorien/Eintraege CRUD fuer GM/Admin
- PWA-Basis: Manifest, Service Worker, Offline-Lesen (Szenen/Charaktere), Offline-Post-Queue
- Security-Basis: Validation, Policies, CSRF, Auth-Middleware, Rate Limiting

## Produkt-Leitlinien

- Primaerziel: immersives, asynchrones Geschichtenerzaehlen (PbP)
- Regelkomplexitaet: leichtes d100-/Prozent-System als Unterstuetzung, nicht als Selbstzweck
- UX-Ziel: Roman-Gefuehl statt Regelbogen-Overload
- Versionspflege: Bei produktiven Aenderungen `APP_VERSION` anheben, damit der Footer den aktuellen Stand zeigt.

## Produkt-Entscheidungen (Maerz 2026)

- Regelwerk, Weltwissen und Spielanleitung bleiben getrennte Wissensbereiche
- Wissenszentrum ist zentrale Einstiegsseite (`/wissen`)
- Enzyklopaedie wird redaktionell durch GM/Admin gepflegt
- Charakter-Erstellung mit zwei Modi:
  - `Real-World Anfaenger` (aktiv, Spezies-Zwang: Mensch)
  - `Native aus Vhal'Tor` (aktiv)

## Tech Stack

- PHP 8.5+
- Laravel 12/13 kompatibel (Skeleton-basiert)
- MySQL / MariaDB (Produktion und empfohlen lokal)
- SQLite nur optional fuer einzelne lokale Test-Setups
- Tailwind CSS (Vite)
- Blade Templates
- Keine zusätzlichen Frontend-Frameworks

## Lokale Entwicklung (Anfänger-freundlich)

### 1) Voraussetzungen

- PHP CLI installiert (`php -v`)
- Composer installiert (`composer -V`)
- Node + npm installiert (`node -v`, `npm -v`)

### 2) Projekt installieren

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

### 3) Datenbank konfigurieren (.env)

Empfohlen (wie Produktion):

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=chroniken_der_asche
DB_USERNAME=root
DB_PASSWORD=
```

### 4) Datenbank migrieren

```bash
php artisan migrate
```

### 5) App starten

Terminal 1:
```bash
php artisan serve
```

Terminal 2:
```bash
npm run dev
```

Dann im Browser öffnen:
- `http://127.0.0.1:8000`

Wichtig: Für Laravel **nicht** VSCode „Live Server“ verwenden. Nutze `php artisan serve`.

## Qualität / Tests

Gesamtsuite ausführen:

```bash
php artisan test
```

Code-Style:

```bash
./vendor/bin/pint
```

Produktionsbuild:

```bash
npm run build
```

Hinweis: `npm run build` synchronisiert automatisch `public/js/character-sheet.global.js` aus `resources/js/character-sheet.js` (keine manuelle Doppelpflege mehr).

## Rollenmodell

- `player`: Charaktere verwalten, in sichtbaren/open Szenen posten
- `gm`: Kampagnen/Szenen verwalten, Moderation
- `admin`: wie GM + erweiterte globale Rechte
- `co_gm` (kampagnenspezifisch): Kampagne/Szenen/Moderation in dieser Kampagne

## PWA-Hinweise

- Manifest: `public/manifest.webmanifest`
- Service Worker: `public/sw.js`
- Offline-Seite: `public/offline.html`
- Install-Button erscheint nur, wenn Browser-Install-Prompt verfügbar ist.
- Offline-Post-Queue nutzt IndexedDB und Sync/Fallback-Trigger.

## Crawler / KI-Bot Schutz

- `public/robots.txt` sperrt Crawling fuer alle Bots (`Disallow: /`).
- `X-Robots-Tag` wird serverseitig gesetzt (Middleware + `.htaccess` Fallback).
- Meta-Tags `robots`, `googlebot`, `bingbot` stehen auf `noindex`.
- Bekannte Search-/KI-Bot User-Agents werden mit `403` geblockt.
- Schalter per ENV:
  - `PRIVACY_NOINDEX_HEADERS=true`
  - `PRIVACY_BLOCK_KNOWN_BOTS=true`
  - optional `PRIVACY_X_ROBOTS_TAG=...`

Hinweis: Das ist Best-Effort. Vollstaendiger Schutz gegen Scraping erfordert zusaetzliche Webserver-/WAF-Regeln.

## Wichtige Routen

- Landing: `/`
- Wissenszentrum: `/wissen`
- Enzyklopaedie: `/wissen/enzyklopaedie`
- Enzyklopaedie Admin (nur GM/Admin): `/wissen/enzyklopaedie/admin/kategorien`
- Dashboard: `/dashboard`
- Kampagnen: `/campaigns`
- Szenen-Abos: `/scene-subscriptions`
- Bookmarks: `/bookmarks`
- Mitteilungen: `/notifications`
- GM Hub: `/gm`

## Bekannte Grenzen (Beta)

- Keine Realtime-Websockets (asynchrones PbP ist primär HTTP-basiert)
- Push Notifications sind vorbereitet, aber noch nicht final integriert
- Keine externe Medien-CDN-Optimierung (lokaler Speicher/Disk)

## Deployment-Notiz (Plesk)

- `.env` auf Produktiv-URL/DB setzen
- `php artisan migrate --force`
- `npm run build`
- Webroot auf `public/` zeigen lassen

## Deployment via GitHub + Plesk

- Anfänger-Guide: `docs/PLESK_DEPLOYMENT_FUER_ANFAENGER.md`
- GitHub->Plesk Setup: `docs/GITHUB_PLESK_SETUP.md`
- Post-Deploy Script (für Plesk Git Actions): `scripts/plesk_post_deploy.sh`
