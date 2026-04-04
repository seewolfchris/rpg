# C76-RPG

C76-RPG ist eine deutschsprachige Play-by-Post-(PbP)-RPG-Plattform mit Multi-World-Konzept, asynchronen Kampagnen/Szenen, Moderation, Benachrichtigungen und PWA-Basis.
Die Startwelt `Chroniken der Asche` bleibt erhalten und ist Teil eines mehrweltfaehigen Produktmodells.
Lizenz: proprietaer (`LICENSE`).
Security-Header werden zentral in `App\Http\Middleware\ApplySecurityHeaders` gesetzt.

## Releases

**Standard-Flow: `scripts/release_flow.sh vX.Y-beta --world <slug> --archive`**

Kurzvarianten:
- Lokal ohne Git-Write: `scripts/release_flow.sh vX.Y-beta --dry-run --iter 500 --archive`
- Ohne Perf-Gate: `scripts/release_flow.sh vX.Y-beta --skip-perf`
- Mit expliziten Skip-Gates (nur bei begruendetem Sonderfall): `--skip-backend-tests`, `--skip-js-tests`, `--skip-e2e-tests`, `--skip-build`, `--skip-smoke`

Wichtige Regeln:
- Version muss dem Format `vX.Y`, `vX.Y-beta` oder `vX.Y-rcN` entsprechen.
- `--world <slug>` ist Pflicht fuer echte Perf-Laeufe (wenn weder `--dry-run` noch `--skip-perf` gesetzt ist).
- Perf-Enforce ist standardmaessig aktiv fuer stabile Tags (`vX.Y`) und aus fuer `-beta`/`-rcN`; ueberschreibbar per `--enforce-perf` bzw. `--no-enforce-perf`.
- Perf-Gate schreibt immer `docs/PERFORMANCE-POSTS-LATEST-BY-ID-GATE-LATEST.md`.
- Mit `--archive` wird zusaetzlich ein UTC-Archiv geschrieben:
  - `docs/PERFORMANCE-POSTS-LATEST-BY-ID-GATE-YYYYMMDDTHHMMSSZ.md`
- Default-Budgets fuer `posts.latest_by_id` (override via `PERF_*`):
  - Warnung: `median > 25 ms`, `p99 > 120 ms`
  - Fail: `median > 40 ms`, `p99 > 180 ms`
- Runtime-Hint bleibt report-basiert (keine automatische `.env`-Mutation):
  - Normalfall (echtes Median/P99): `Median <=95%` und `P99 <=110%` gegen Vorfenster.
  - Fallback (`avg->Median`, `p95->P99`): strengere Schwellen `<=90%` und `<=105%`.
- Benchmarks sind nur innerhalb derselben DB-Engine direkt vergleichbar (SQLite != MySQL).
- Roter Perf-Status ist report-only, solange Perf-Enforce aus ist. Mit Perf-Enforce endet ROT als non-zero.

## Dokumentations-Uebersicht

Source of Truth je Thema:
- Einstieg + Kernkommandos: `README.md`
- Planung/Statusachsen: `ROADMAP.md`
- Release-Flow + Gates: `docs/RELEASE-CHECKLISTE.md`
- Betrieb/Incident + Security-Header-Anbindung: `docs/OPERATIONS_RUNBOOK.md`
- Architekturentscheidungen: `docs/adr/`
- Betriebs-/Setup-Ergaenzungen: `docs/PLESK_DEPLOYMENT_FUER_ANFAENGER.md`, `docs/GITHUB_PLESK_SETUP.md`

## Beta-Status

Stand: **Release-Beta `v0.28-beta`** (funktional, getestet, build-faehig)

Changelog:
- v0.28-beta (2026-04-04): Release-Welle A/B/C abgeschlossen mit produktkonformen Composer-Metadaten (`c76/rpg`), zentraler Security-Header-Middleware, modularem Auth-Routing ohne Vertragsdrift, Architektur-Guardrails, MySQL-Critical-Gates, standardisiertem Domain-Event-Logging und Doku-Konsolidierung.
- v0.27-beta (2026-04-03): Hardening-Nachzug mit konsolidiertem `CampaignParticipantResolver` entlang Requests/Domain-Services, gehaerteten Post-/World-Invarianten (inkl. Default-Welt-Loeschschutz), atomarem Invite-Upsert (`1062`-Fallback) und separatem CI-MySQL-Concurrency-Job.
- v0.27-beta (2026-04-02): Hardening-Release mit atomarem Post-Update-Flow, PWA-Privacy-Boundary bei Auth-Wechseln, idempotentem Reaction-Upsert, kanonischem Invite-Weltkontext, Redis-Produktionsdefaults, scope-korrektem GM-/Dashboard-Count, paginiertem Notification-Center, gesplitteten Web-Routen, modularisierter Authorization-Matrix und browserbasierten Playwright-E2E-Flows fuer Offline/Auth-Queue-Retry.
- v0.26-beta (2026-04-02): Architektur-Konsolidierung nach Multi-Welt-Rollout: d100-Probenpfad vereinheitlicht, Moderations-Readlogik dedupliziert, Character-/Progression-Autorisierung policy-first konsolidiert, Bookmark-Jump-Querylast reduziert, Character-Actions auf request-freie Inputs umgestellt und Payload-Typisierung bis inkl. Request-Grenze nachgezogen; PHPStan-Baseline auf null reduziert.
- v0.25-beta (2026-03-29): Immersion-Upgrade Phasen 1-4 abgeschlossen (World-Theme-Resolver + CSS-Variablen am Root, erweiterter Romanmodus inkl. Fullscreen/Progress-Lesezeichen/Shortcut-Navigation, Hero-/Card-Parallax-light mit reduced-motion-Fallback, PWA-Offline-/Queue-Narrativ, DE-first Sprachkonsistenz und Fokus-/Scroll-Polish)
- v0.24-beta: Finaler Immersion-Polish – Landing-Hero mit Szenen-Teaser, romanhafter Thread-Lesemodus, konsistente World/Character-Cards, globale Typografie-/Spacing-Glättung
- v0.24-beta (Stability-Update 2026-03-23): Harte Service-Invarianten fuer Probe/Inventar (Welt + Kampagnen-Teilnahme), robuste Atomic-/Compensation-Semantik in `StorePostService` und `CreateCharacterAction`, Queue-Retry-Jobs fuer fehlgeschlagene Szenen-/Mention-Benachrichtigungen

Letzte lokale Verifikation:
- `php artisan test --without-tty --do-not-cache-result --exclude-group=mysql-concurrency --exclude-group=mysql-critical` -> **370 passed, 2134 assertions** (Stand: 2026-04-04)
- `node --test tests/js/*.mjs` -> **19 passed** (Stand: 2026-04-04)
- `composer analyse` -> **keine Fehler** (Stand: 2026-04-04)
- `npm run test:e2e` -> **4 passed** (Stand: 2026-04-04)
- `npm run build` -> **gruen** (Stand: 2026-04-02)

Enthalten:
- Auth: Registrierung/Login/Logout (Breeze-Style, Blade)
- Charaktersystem: CRUD, Eigenschaften, Biografie, Avatar-Upload, Ownership-Checks
  - Create-Flow via `CreateCharacterAction` + `AttributeNormalizer` + `AvatarService` (transactional, after-commit Avatar-Finalisierung)
- Kampagnen/Szenen: Erstellung, Sichtbarkeit, Filter, Rollen (Owner/Co-GM/Player)
- Posts: IC/OOC, Markdown/BBCode/Plain, Spoiler, Edit-History (Revisionen)
- Moderation: Ausstehend/Freigegeben/Abgelehnt, Audit-Log, GM-Queue, Bulk-Aktionen
- Pinning: Wichtige Posts in Szenen anpinnen/entpinnen
- Einladungen: Ausstehend/Annehmen/Ablehnen inkl. Rollen (Player/Co-GM)
- Read-Tracking: Ungelesen-Status, Read/Unread-Aktionen, Jump-Links
- Bookmarks: Szenen-Bookmark je User inkl. Jump auf Post/Page
- GM-Proben im Post: GM-only mit Anlass/Ziel-Held/Modifikator, d100-Rollmode, Log in DB und direkter LE/AE-Persistenz
- Benachrichtigungen: In-App + Mail + Browser Web Push (VAPID, weltkontextfaehig)
- Gamification: Punkte für freigegebene Posts
- Wissenszentrum: Uebersicht, Wie-spielt-man, Regelwerk, Enzyklopaedie
- Enzyklopaedie-Admin: Kategorien/Eintraege CRUD fuer GM/Admin
- Multi-Welt-Plattform:
  - Weltmodell + Admin-CRUD (`worlds`)
  - Weltkontext-Routing unter `/w/{world}/...`
  - Legacy-Redirects (`301`) fuer kampagnen-/szenenbezogene Altpfade ohne Weltsegment
  - Globale Wissensrouten ohne Weltsegment bleiben direkt erreichbar (`/wissen`, `/wissen/*`)
  - Weltbindung auf Kampagnen, Charaktere und Enzyklopaedie-Kategorien
- PWA-Basis: Manifest, Service Worker, Offline-Lesen (Szenen/Charaktere), Offline-Post-Queue
- Security-Basis: Validation, Policies, CSRF, Auth-Middleware, Rate Limiting

## Produkt-Leitlinien

- Primaerziel: immersives, asynchrones Geschichtenerzaehlen (PbP)
- Regelkomplexitaet: leichtes d100-/Prozent-System als Unterstuetzung, nicht als Selbstzweck
- UX-Ziel: Roman-Gefuehl statt Regelbogen-Overload
- Versionspflege: Bei produktiven Aenderungen `APP_VERSION` anheben, damit der Footer den aktuellen Stand zeigt.

## Produkt-Entscheidungen (Maerz 2026)

- Plattform-Branding: **C76-RPG** (nicht mehr weltgebundenes Branding auf der Landingpage)
- Welten sind kampagnengebunden (eine Kampagne gehoert genau zu einer Welt)
- Charaktere und Enzyklopaedie-Kategorien sind ebenfalls weltgebunden
- Wissenszentrum bleibt zentrale Einstiegsseite, jedoch immer im Weltkontext
- Enzyklopaedie wird redaktionell durch GM/Admin gepflegt (pro Welt getrennt)

## Tech Stack

- PHP 8.5+
- Laravel 12 (Skeleton-basiert)
- MySQL / MariaDB (Produktion und empfohlen lokal)
- SQLite nur optional fuer einzelne lokale Test-Setups
- Tailwind CSS (Vite)
- Blade Templates
- HTMX 2.x + Alpine.js 3.x (lokal via Vite)
- Kein Livewire, kein Inertia, kein Vue/React

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
DB_DATABASE=c76_rpg
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
php artisan optimize:clear
php artisan test
```

Wichtig:
- Falls vorher `config:cache`, `route:cache`, `event:cache` oder `view:cache` aktiv war, immer zuerst `php artisan optimize:clear`.
- Sonst koennen Feature-Tests mit `419` fehlschlagen (CSRF/Environment-Mismatch).

CI lokal spiegeln:

```bash
composer validate --strict
composer analyse
php artisan test --without-tty --do-not-cache-result --exclude-group=mysql-concurrency --exclude-group=mysql-critical
npm run test:js
npm run test:e2e
npm run build
```

Optional fuer produktnahe DB-Pfade (lokal mit MySQL):

```bash
php artisan test --without-tty --do-not-cache-result --group=mysql-concurrency
php artisan test --without-tty --do-not-cache-result --group=mysql-critical
```

Hinweis zur DB in Tests/CI:
- Produktion und lokale Standard-Entwicklung laufen auf MySQL/MariaDB.
- Der Haupt-Testlauf in CI nutzt SQLite in-memory (`phpunit.xml`) fuer schnelle Reproduzierbarkeit.
- Zusätzlich laeuft ein separater CI-Job gegen MySQL fuer `--group=mysql-concurrency` und `--group=mysql-critical`.

Statische Analyse (Larastan/PHPStan):

```bash
composer analyse
```

Hinweis: Altbefunde sind als Startpunkt in `phpstan-baseline.neon` erfasst. Neue Fehler brechen den Lauf.

Performance-EXPLAIN fuer Welt-Hotpaths:

```bash
php artisan perf:world-hotpaths --world=<world-slug>
```

posts.latest_by_id Recheck + Delta-Report:

```bash
scripts/perf_posts_latest_by_id.sh
```

Optional mit Parametern:

```bash
PERF_WORLD_SLUG=<world-slug> PERF_ITERATIONS=600 scripts/perf_posts_latest_by_id.sh
```

Das Skript schreibt:
- den datierten Laufreport (Default: `docs/PERFORMANCE-POSTS-LATEST-BY-ID-YYYY-MM-DD.md`)
- den jeweils aktuellen Vergleichsreport (`docs/PERFORMANCE-POSTS-LATEST-BY-ID-LATEST.md`, inkl. Delta zum letzten Lauf)

Release-Perf-Gate (wird vom Standard-Flow aufgerufen):

```bash
scripts/release_perf_gate.sh
```

Das Skript:
- fuehrt den Benchmark-Recheck aus
- bewertet Delta gegen Schwellwerte (GRUEN/GELB/ROT)
- schreibt `docs/PERFORMANCE-POSTS-LATEST-BY-ID-GATE-LATEST.md`
- archiviert optional nach `PERF_GATE_ARCHIVE_OUT`, wenn gesetzt
- liefert non-zero nur bei technischen Fehlern

Optionaler Runtime-Toggle fuer den MySQL-Hotpath `posts.latest_by_id`:

```env
PERF_POSTS_LATEST_BY_ID_FORCE_INDEX=true
PERF_POSTS_LATEST_BY_ID_FORCE_INDEX_NAME=posts_scene_id_id_idx
```

Hinweis: Standard ist `false`. Nur aktivieren, wenn der Gate-/Benchmark-Lauf im Zielsystem stabilen Vorteil zeigt.

Code-Style:

```bash
./vendor/bin/pint
```

Produktionsbuild:

```bash
npm run build
```

Hinweis: `npm run build` synchronisiert automatisch `public/js/character-sheet.global.js` aus `resources/js/character-sheet.js` (keine manuelle Doppelpflege mehr).

Release-Metadaten (Version/Build/Doku) vorbereiten:

```bash
scripts/release_prepare.sh --version v0.28-beta
```

Optional auch lokale `.env` aktualisieren:

```bash
scripts/release_prepare.sh --version v0.28-beta --update-dotenv
```

Kompletter lokaler Release-Flow (inkl. `release_prepare`, Quality Gates, Perf-Gate, Smoke):

```bash
scripts/release_flow.sh v0.28-beta --world chroniken-der-asche --archive
```

Release-Smoke automatisiert:

```bash
scripts/release_smoke.sh
```

Optional gegen laufende Staging/Prod-Instanz:

```bash
SMOKE_BASE_URL="https://example.org" SMOKE_WORLD_SLUG="<world-slug>" SMOKE_REPORT_OUT="docs/SMOKE-PASS-STAGING-PROD.md" scripts/release_smoke.sh
```

Hinweis:
- `SMOKE_WORLD_SLUG` kann entfallen, wenn `WORLD_DEFAULT_SLUG` in `.env` gesetzt ist.
- Bei externer `SMOKE_BASE_URL` startet `scripts/release_smoke.sh` keinen lokalen `artisan serve`.
- Im Standard (`SMOKE_MODE=http`) ist HTTP ein hartes Gate; bei nicht erreichbarem `/up` endet der Lauf non-zero.
- Ein Artisan-Fallback ist nur explizit aktivierbar (`SMOKE_ALLOW_ARTISAN_FALLBACK=1`).

Optional ohne HTTP-Checks (nur Route-/Environment-Basischeck):

```bash
SMOKE_MODE=artisan scripts/release_smoke.sh
```

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
- Bei `419` versucht der Service Worker automatisch ein Re-Signing (neuer CSRF-Token + aktuelle Form-Action) und sendet den Queue-Post erneut.
- Bei `401`/`419`/`429` werden Queue-Eintraege nicht verworfen, sondern mit Backoff (`retry_count`, `next_retry_at`) geplant.
- Bei `4xx` (ausser `401`/`419`/`429`) wandern Queue-Eintraege in `postDeadLetters` statt verworfen zu werden.
- Bei `5xx` (oder Netzwerkfehler `status=0`) erfolgen max. `5` Retries; danach wandert der Eintrag in `postDeadLetters`.
- Dead-Letter-Eintraege speichern ein kurzes `error_summary` (z. B. `Text zu kurz` oder `Server-Fehler (500)`), das im UI vor dem Import angezeigt wird.
- Service Worker wird versionsgebunden registriert (`/sw.js?v=<APP_VERSION>-<APP_BUILD>`), Cache-Namen sind versionsbasiert.
- Relevante Service-Worker-Events fuer die UI:
  - `POST_SYNC_AUTH_RETRY`: Re-Signing erfolgreich, erneuter Sendeversuch startet.
  - `POST_SYNC_DEAD_LETTERED`: Queue-Eintrag wurde mit `error_summary` in Entwuerfe/Fehler verschoben.
  - `POST_SYNC_RETRY_SCHEDULED`: Retry mit naechstem Zeitpunkt geplant.
  - `POST_SYNC_AUTH_REQUIRED`: Session/CSRF nicht erneuerbar, Nutzeraktion (Login) erforderlich.

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


## Rate Limiting fuer mutierende Routen

Folgende zentrale Limiter sind fuer schreibende Endpunkte aktiv:

- `writes`: **30 Requests/Minute je Nutzer/IP** fuer allgemeine Schreibaktionen (POST/PATCH/DELETE), z. B. Kampagnen/Szenen/Posts/Einladungen/Bookmarks/Subscriptions/Logout.
- `moderation`: **15 Requests/Minute je Nutzer/IP** fuer Moderationsaktionen, z. B. Post-Freigabe/Ablehnung, Pin/Unpin, GM-Bulk-Moderation und Enzyklopaedie-Admin-CRUD.
- `notifications`: **20 Requests/Minute je Nutzer/IP** fuer mutierende Benachrichtigungs-Routen (`read`, `read-all`, Preferences-Update).
- `webpush-subscriptions`: **20 Requests/Minute je Nutzer/IP und Welt** fuer `/api/webpush/subscribe` und `/api/webpush/unsubscribe`.

Erreicht ein Client das Limit, antwortet Laravel mit HTTP `429 Too Many Requests`.

## Wichtige Routen

- Landing: `/`
- Weltkatalog: `/welten`
- Canonical Weltkontext: `/w/{world}/...`
- Wissenszentrum (canonical): `/w/{world}/wissen`
- Enzyklopaedie (canonical): `/w/{world}/wissen/enzyklopaedie`
- Enzyklopaedie Admin (nur GM/Admin): `/w/{world}/wissen/enzyklopaedie/admin/kategorien`
- Dashboard: `/dashboard`
- Kampagnen (canonical): `/w/{world}/campaigns`
- Szenen-Abos (canonical): `/w/{world}/scene-subscriptions`
- Bookmarks (canonical): `/w/{world}/bookmarks`
- Mitteilungen: `/notifications`
- GM Hub (global): `/gm`
- GM Moderation (canonical): `/w/{world}/gm/moderation`
- Rechtliche Seiten: zentral unter `https://c76.org/impressum/` und `https://c76.org/datenschutz/` (Footer-Links)

Hinweis:
- Globale Wissensseiten bleiben ohne Weltsegment erreichbar (`/wissen`, `/wissen/*`).
- Kampagnen-/Szenen-Legacy-Pfade ohne Weltsegment leiten per `301` auf die passende Welt-URL um.

## Bekannte Grenzen (Beta)

- Keine Realtime-Websockets (asynchrones PbP ist primär HTTP-basiert)
- Web Push benoetigt Browser-Permission und aktiven Service Worker pro Endgeraet
- Keine externe Medien-CDN-Optimierung (lokaler Speicher/Disk)

## Deployment-Notiz (Plesk)

- `.env` auf Produktiv-URL/DB setzen
- `PHP_BIN=/opt/plesk/php/8.5/bin/php` setzen (Projekt benoetigt PHP `>= 8.5`)
- `$PHP_BIN artisan migrate --force`
- `npm run build`
- Webroot auf `public/` zeigen lassen

## Deployment via GitHub + Plesk

- Anfänger-Guide: `docs/PLESK_DEPLOYMENT_FUER_ANFAENGER.md`
- GitHub->Plesk Setup: `docs/GITHUB_PLESK_SETUP.md`
- Post-Deploy Script (für Plesk Git Actions): `scripts/plesk_post_deploy.sh`
- CI Workflow: `.github/workflows/ci.yml`
