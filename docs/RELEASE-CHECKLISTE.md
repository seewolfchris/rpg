# Release-Checkliste (C76-RPG)

Ziel: Jeder Release laeuft gleich ab, ohne Raten und ohne vergessene Schritte.

## 1. Lokal vorbereiten

- `git pull --rebase origin main`
- Alle geplanten Aenderungen finalisieren.
- Sicherstellen, dass `APP_VERSION` fuer den Release feststeht (z. B. `v0.20-beta`).

## 2. Qualitaet lokal pruefen

- Composer-Validierung:
  - `composer validate --strict`
- Statische Analyse:
  - `composer analyse`
- Tests:
  - `php artisan test --without-tty --do-not-cache-result`
- Frontend-Build:
  - `npm run build`

Nur wenn alles gruen ist, weiter.

## 3. Version aktualisieren

- Laufende lokale Instanz:
  - `.env`: `APP_VERSION=vX.XX-beta`
- Repo-Vorlage fuer neue Umgebungen:
  - `.env.example`: `APP_VERSION=vX.XX-beta`
- Optional:
  - `.env`: `APP_BUILD=<kurzer commit hash>`

Beispiel fuer `APP_BUILD`:

```bash
git rev-parse --short HEAD
```

## 3b. Web Push (aktiv seit `v0.20-beta`)

- VAPID-Keys muessen gesetzt sein:
  - `VAPID_PUBLIC_KEY`
  - `VAPID_PRIVATE_KEY`
  - optional `VAPID_SUBJECT`
- DB-Connection:
  - Standard: folgt `DB_CONNECTION` (empfohlen)
  - optionaler Override nur falls noetig: `WEBPUSH_DB_CONNECTION=mysql`
- Optional neu generieren:
  - `php artisan webpush:vapid`

## 4. Commit und Push

```bash
git status
git add -A
git commit -m "release: vX.XX-beta"
git push origin main
```

## 5. Deploy auf Plesk

Wenn Git-Webhook aktiv ist: Deployment startet automatisch.  
Wenn manuell noetig: In Plesk Git auf `Bereitstellen` klicken.

Post-Deploy muss mit PHP 8.5 laufen:

```bash
cd /var/www/vhosts/c76.org/rpg.c76.org
PHP_BIN=/opt/plesk/php/8.5/bin/php /bin/bash scripts/plesk_post_deploy.sh
```

Wenn `.env` nach dem Deploy angepasst wurde (z. B. VAPID, Version/Build), danach einmal:

```bash
PHP_BIN=/opt/plesk/php/8.5/bin/php
$PHP_BIN artisan optimize:clear
$PHP_BIN artisan config:cache
```

## 6. Smoke-Test nach Deploy (5 Minuten)

- Automatisierter Basissmoke:
  - `SMOKE_START_SERVER=0 SMOKE_BASE_URL="https://rpg.c76.org" SMOKE_WORLD_SLUG="chroniken-der-asche" SMOKE_REPORT_OUT="docs/SMOKE-PASS-STAGING-PROD.md" scripts/release_smoke.sh`
  - Alternativ rein lokal/ohne HTTP-Server: `SMOKE_MODE=artisan scripts/release_smoke.sh`
- Das Skript kann einen Markdown-Report schreiben (`SMOKE_REPORT_OUT=...`).
- Login/Logout funktioniert.
- Dashboard laedt.
- Weltkatalog und Weltkontext-Routing funktionieren (`/welten`, `/w/{world}/...`).
- Legacy-URLs liefern `301` auf Weltkontext (`/wissen`, `/wissen/enzyklopaedie`, `/hilfe`).
- Charakter-Erstellung laedt ohne JS-Fehler.
- GM-Post mit Probe funktioniert (inkl. LE/AE-Update am Zielcharakter).
- Footer zeigt korrekte Version (`Build: vX.XX-beta`).

## 6b. Performance-EXPLAIN (Staging/Prod, empfohlen)

- `php artisan perf:world-hotpaths --world=chroniken-der-asche --out=docs/PERFORMANCE-PASS-STAGING-PROD.md`
- Report pruefen auf Index-Nutzung der Hotpaths (`posts`, `scene_subscriptions`, `campaign_invitations`).
- Optional fuer `posts.latest_by_id`:
  - `php artisan perf:posts-latest-by-id-benchmark --world=chroniken-der-asche --iterations=400 --out=docs/PERFORMANCE-POSTS-LATEST-BY-ID-STAGING-PROD.md`

## 7. Dokumentation aktualisieren

- `docs/PROJEKT-ÜBERSICHT.md` auf aktuellen Stand bringen:
  - Release-Stand
  - wichtige Aenderungen
  - offene Prioritaeten

## 8. Kurzprotokoll (empfohlen)

Fuer jeden Release einmal notieren:
- Version
- Commit-Hash
- Zeitpunkt Deploy
- Ergebnis Smoke-Test
- offene Nacharbeiten
- Optional/empfohlen: Link auf den Smoke-Report (`docs/SMOKE-PASS-STAGING-PROD.md`).
