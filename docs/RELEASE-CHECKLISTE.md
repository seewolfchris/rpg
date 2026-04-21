# Release-Checkliste (C76-RPG)

Ziel: Jeder Release läuft gleich ab, ohne Raten und ohne vergessene Schritte.

## 0. Empfohlener One-Command-Flow

Siehe `README.md` (Dokumentationssektion) fuer den aktuellen Einstieg und die verlinkte Release-Doku.

Die folgenden Punkte sind der manuelle Referenzablauf bzw. für Sonderfälle.

## 1. Lokal vorbereiten

- `git pull --rebase origin main`
- Alle geplanten Änderungen finalisieren.
- Sicherstellen, dass `APP_VERSION` für den Release feststeht (z. B. `v0.30-beta`).

## 2. Qualität lokal prüfen

- Cache-Snapshot zurücksetzen (wichtig vor lokalen Feature-Tests):
  - `php artisan optimize:clear`
- Composer-Validierung:
  - `composer validate --strict`
- Statische Analyse:
  - `composer analyse`
- Tests:
  - `php artisan test --without-tty --do-not-cache-result --exclude-group=mysql-concurrency --exclude-group=mysql-critical`
  - Optional lokal bei verfügbarem MySQL:
    - `php artisan test --without-tty --do-not-cache-result --group=mysql-concurrency`
    - `php artisan test --without-tty --do-not-cache-result --group=mysql-critical`
- Frontend-JS Regression:
  - `npm run test:js`
- Browser-E2E (Offline/Auth-Boundary/Queue-Retry):
  - `npm run test:e2e`
- Frontend-Build:
  - `npm run build`

Nur wenn alles grün ist, weiter.

Hinweis:
- Wenn zuvor `config:cache`, `route:cache`, `event:cache` oder `view:cache` aktiv war, können Feature-Tests sonst mit `419` fehlschlagen.

## 3. Version aktualisieren

- Automatisiert (empfohlen):

```bash
scripts/release_prepare.sh --version vX.XX-beta --build "$(git rev-parse --short HEAD)"
```

- Optional auch lokale `.env` setzen:

```bash
scripts/release_prepare.sh --version vX.XX-beta --build "$(git rev-parse --short HEAD)" --update-dotenv
```

- Manuell (falls nötig):
  - Laufende lokale Instanz:
    - `.env`: `APP_VERSION=vX.XX-beta`
  - Repo-Vorlage für neue Umgebungen:
    - `.env.example`: `APP_VERSION=vX.XX-beta`
  - Optional:
    - `.env`: `APP_BUILD=<kurzer commit hash>`
  - Hinweis Service Worker:
    - SW wird mit `/sw.js?v=<APP_VERSION>-<APP_BUILD>` registriert.
    - Bei Versionssprung immer `APP_VERSION` (und idealerweise `APP_BUILD`) mitziehen, damit alte SW-Caches sicher invalidiert werden.

## 3b. Web Push (aktiv seit `v0.20-beta`)

- VAPID-Keys müssen gesetzt sein:
  - `VAPID_PUBLIC_KEY`
  - `VAPID_PRIVATE_KEY`
  - optional `VAPID_SUBJECT`
- DB-Connection:
  - Standard: folgt `DB_CONNECTION` (empfohlen)
  - optionaler Override nur falls nötig: `WEBPUSH_DB_CONNECTION=mysql`
- Optional neu generieren:
  - `php artisan webpush:vapid`

## 4. Commit und Push

```bash
git status
git add -A
git commit -m "release: vX.XX-beta"
git push origin main
```

Wichtig:
- Temporäre Dateien wie `.goutputstream-*` nicht committen.

## 5. Deploy auf Plesk

Wenn Git-Webhook aktiv ist: Deployment startet automatisch.  
Wenn manuell nötig: In Plesk Git auf `Bereitstellen` klicken.

Post-Deploy muss mit PHP 8.5 laufen:

```bash
cd /var/www/vhosts/c76.org/rpg.c76.org
PHP_BIN=/opt/plesk/php/8.5/bin/php /bin/bash scripts/plesk_post_deploy.sh
```

Queue-Retry in Produktion sicherstellen:

```bash
# .env
QUEUE_CONNECTION=redis
QUEUE_AFTER_COMMIT=true
CACHE_STORE=redis
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
TRUSTED_PROXIES=<proxy-ip/cidr,...>
SECURITY_HSTS_MAX_AGE=31536000
# Optional bei stabiler Redis-Session-Infrastruktur:
# SESSION_DRIVER=redis

# Worker (Scheduled Task/Prozess)
PHP_BIN=/opt/plesk/php/8.5/bin/php
$PHP_BIN artisan queue:work --queue=default --tries=4 --sleep=1 --timeout=90
```

Preflight-Werte vor Deploy gegenpruefen:

```bash
grep -E '^(APP_ENV|SESSION_SECURE_COOKIE|QUEUE_CONNECTION|QUEUE_AFTER_COMMIT|TRUSTED_PROXIES|SECURITY_HSTS_MAX_AGE)=' .env
```

Wenn `.env` nach dem Deploy angepasst wurde (z. B. VAPID, Version/Build), danach einmal:

```bash
PHP_BIN=/opt/plesk/php/8.5/bin/php
$PHP_BIN artisan optimize:clear
$PHP_BIN artisan config:cache
```

## 6. Smoke-Test nach Deploy (5 Minuten)

- Automatisierter Basissmoke:
  - `SMOKE_BASE_URL="https://rpg.c76.org" SMOKE_WORLD_SLUG="<world-slug>" SMOKE_REPORT_OUT="docs/SMOKE-PASS-STAGING-PROD.md" scripts/release_smoke.sh`
  - Alternativ rein lokal/ohne HTTP-Server: `SMOKE_MODE=artisan scripts/release_smoke.sh`
- Das Skript kann einen Markdown-Report schreiben (`SMOKE_REPORT_OUT=...`).
- Wenn `SMOKE_WORLD_SLUG` fehlt, wird `WORLD_DEFAULT_SLUG` aus `.env` verwendet.
- Bei externer `SMOKE_BASE_URL` wird kein lokaler `artisan serve` gestartet.
- Standardverhalten: `SMOKE_MODE=http` ist ein echtes HTTP-Gate; wenn `/up` nicht erreichbar ist, endet der Lauf non-zero.
- Optionaler Fallback ist nur explizit aktiv (`SMOKE_ALLOW_ARTISAN_FALLBACK=1`).
- Login/Logout funktioniert.
- Dashboard lädt.
- Weltkatalog und Weltkontext-Routing funktionieren (`/welten`, `/w/{world}/...`).
- Globale Wissensseiten laden (`/wissen`, `/wissen/enzyklopaedie`), `/hilfe` liefert `302` auf `/wissen`.
- Kampagnen-/Szenen-Legacy-Pfade ohne Weltsegment liefern `301` auf den Weltkontext.
- Charakter-Erstellung lädt ohne JS-Fehler.
- Offline-Queue-Fehlerpfad prüfen (kurz):
  - Validierungsfehler (`422`) erzeugt einen Eintrag im Bereich "Offline-Entwürfe mit Fehler".
  - UI zeigt `error_summary` (z. B. `Text zu kurz`) und erlaubt "In Editor übernehmen" (Anhängen/Ersetzen/Abbrechen).
- GM-Post mit Probe funktioniert (inkl. LE/AE-Update am Zielcharakter).
- Footer zeigt korrekte Version (`Build: vX.XX-beta`).

## 6a. Immersion Phase-A Gate (wenn Welle 1/2 ausgerollt wird)

- One-Command Deploy-Flow (empfohlen):
  - `PHP_BIN=/opt/plesk/php/8.5/bin/php scripts/release_phase_a_flow.sh --base-url "https://rpg.c76.org" --world-slug "<world-slug>" --report-out "docs/SMOKE-PHASE-A.md"`
  - Enthalten: `migrate --force`, `optimize:clear`, `config:cache`, `release_phase_a_smoke.sh` als hartes Go/No-Go-Gate.
  - Deploy-sicherer Default: keine Testausführung auf der Zielumgebung (`--run-test-gates 0`).
- Verbindlicher Gate-Run für DB + Welle 1/2:
  - `PHP_BIN=/opt/plesk/php/8.5/bin/php PHASE_A_BASE_URL="https://rpg.c76.org" PHASE_A_WORLD_SLUG="<world-slug>" PHASE_A_REPORT_OUT="docs/SMOKE-PHASE-A.md" scripts/release_phase_a_smoke.sh`
- Hinweis: `<world-slug>` ist eine aktive Welt (`/w/<world-slug>/...`) oder kommt aus `WORLD_DEFAULT_SLUG`.
- Das Skript prüft:
  - Basis-HTTP-Smoke (`scripts/release_smoke.sh`)
  - Flag-Zustände für Phase A (Welle 3/4 aus)
  - gezielte Immersion-Tests (Mood/Header/Vorgänger, IC-first/OOC, IC-Zitat, Relative Time)
- Optional für bereits aktivierte Welle-3/4-Umgebungen:
  - `PHASE_A_STRICT_FLAGS=0 ... scripts/release_phase_a_smoke.sh`
- Optionaler lokaler Vorab-Lauf inklusive Test-Gates:
  - `scripts/release_phase_a_flow.sh --smoke-mode artisan --skip-migrate --run-test-gates 1 --report-out "docs/SMOKE-PHASE-A-LOCAL.md"`
- Stabilitätsphase (Tag 1-5) als Daily-Gate:
  - `scripts/release_phase_a_stability_check.sh --smoke-mode artisan --report-out "docs/PHASE-A-STABILITY-DAY1.md"`
  - Optional mit produktionsnahem HTTP-Smoke:
    - `scripts/release_phase_a_stability_check.sh --base-url "https://rpg.c76.org" --world-slug "<world-slug>" --smoke-mode http --report-out "docs/PHASE-A-STABILITY-DAY1.md" --smoke-report-out "docs/SMOKE-PHASE-A-DAY1.md"`
  - Wichtig: `release_phase_a_stability_check.sh` benötigt `node` (wegen JS-Draft-Tests). Ohne Node auf dem Zielhost den Stability-Check lokal/CI laufen lassen und auf dem Server nur `release_phase_a_smoke.sh` nutzen.

## 6b. Performance-EXPLAIN (Staging/Prod, empfohlen)

- `php artisan perf:world-hotpaths --world=<world-slug> --out=docs/PERFORMANCE-PASS-STAGING-PROD.md`
- Report prüfen auf Index-Nutzung der Hotpaths (`posts`, `scene_subscriptions`, `campaign_invitations`).
- Empfohlen für `posts.latest_by_id` (inkl. Ampel-Gate):
  - `PERF_WORLD_SLUG=<world-slug> PERF_ITERATIONS=400 PERF_REPORT_OUT=docs/PERFORMANCE-POSTS-LATEST-BY-ID-STAGING-PROD.md PERF_LATEST_OUT=docs/PERFORMANCE-POSTS-LATEST-BY-ID-LATEST.md PERF_GATE_OUT=docs/PERFORMANCE-POSTS-LATEST-BY-ID-GATE-LATEST.md scripts/release_perf_gate.sh`
  - Runtime-Hint kommt aus dem Gate-Report (`FORCE_INDEX=1/0`), keine automatische `.env`-Mutation.
  - Gate-Report prüfen: `docs/PERFORMANCE-POSTS-LATEST-BY-ID-GATE-LATEST.md`
  - Default-Budgets (override via `PERF_*`):
    - Warnung: `median > 25 ms`, `p99 > 120 ms`
    - Fail: `median > 40 ms`, `p99 > 180 ms`
  - Wichtig: Messwerte nur innerhalb derselben DB-Engine vergleichen (`sqlite` vs `mysql/mariadb`).
  - Ampel-Interpretation:
    - `GRUEN`: weiter im Release-Flow.
    - `GELB`: weiter möglich, aber Delta beobachten.
    - `ROT`: report-only Signal, solange `PERF_GATE_ENFORCE=0`.
    - `ROT` mit `PERF_GATE_ENFORCE=1`: harter non-zero-Abbruch (Standard bei stabilen Tags im `release_flow.sh`).
- Fallback ohne Gate:
  - `PERF_WORLD_SLUG=<world-slug> PERF_ITERATIONS=400 PERF_REPORT_OUT=docs/PERFORMANCE-POSTS-LATEST-BY-ID-STAGING-PROD.md PERF_LATEST_OUT=docs/PERFORMANCE-POSTS-LATEST-BY-ID-LATEST.md scripts/perf_posts_latest_by_id.sh`
  - Fallback direkt via Artisan:
    - `php artisan perf:posts-latest-by-id-benchmark --world=<world-slug> --iterations=400 --out=docs/PERFORMANCE-POSTS-LATEST-BY-ID-STAGING-PROD.md`

## 7. Dokumentation aktualisieren

- `docs/PROJEKT-ÜBERSICHT.md` auf aktuellen Stand bringen:
  - Release-Stand
  - wichtige Änderungen
  - offene Prioritäten

## 8. Kurzprotokoll (empfohlen)

Für jeden Release einmal notieren:
- Version
- Commit-Hash
- Zeitpunkt Deploy
- Ergebnis Smoke-Test
- offene Nacharbeiten
- Optional/empfohlen: Link auf den Smoke-Report (`docs/SMOKE-PASS-STAGING-PROD.md`).
