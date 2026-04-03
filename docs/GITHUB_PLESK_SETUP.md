# GitHub -> Plesk Setup (ohne Stress)

Diese Anleitung verbindet dein GitHub-Repo mit Plesk, damit du künftig per `git push` deployen kannst.

## Ziel

- Code liegt in GitHub
- Plesk zieht Code direkt aus GitHub
- Nach jedem Deploy laufen Laravel-Kommandos automatisch

## Voraussetzungen

- Repo existiert: `https://github.com/seewolfchris/rpg`
- Branch: `main`
- In Plesk ist die Domain/Subscription vorhanden
- Laravel Toolkit ist aktiv

## 1) Plesk vorbereiten

1. Plesk öffnen
2. `Websites & Domains` -> deine Domain
3. Prüfen: **Document Root** muss auf `public` zeigen
4. `Laravel` (Toolkit) öffnen und App erkennen lassen (falls noch nicht erkannt)

## 2) Git in Plesk verbinden

1. `Websites & Domains` -> `Git`
2. `Repository hinzufügen`
3. URL eintragen: `https://github.com/seewolfchris/rpg.git`
4. Branch: `main`
5. Deployment path: Projekt-Root (nicht `public`)

Hinweis:
- Bei privatem Repo musst du in Plesk GitHub-Zugriff hinterlegen (Token/Passwort je nach Plesk-Version).

## 3) Deploy-Aktion einrichten

In der Git-Erweiterung (Deployment actions / Additional deploy actions) folgendes setzen:

```bash
PHP_BIN=/opt/plesk/php/8.5/bin/php /bin/bash scripts/plesk_post_deploy.sh
```

Dieses Script liegt im Repo und macht:
- `composer install --no-dev`
- prüft `APP_KEY` (fail-fast bei fehlendem/ungueltigem Key)
- prüft `QUEUE_CONNECTION` (fail-fast bei `sync`)
- `php artisan migrate --force`
- `php artisan storage:link`
- Cache clear + cache build

## 4) Server-Umgebung setzen (.env)

In Plesk (Laravel Toolkit oder Dateimanager) `.env` für Produktion setzen.
Nutze als Vorlage: `.env.plesk.example`.

Pflichtwerte:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://deine-domain.tld

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=DEINE_DB
DB_USERNAME=DEIN_DB_USER
DB_PASSWORD=DEIN_DB_PASS
```

Zusatzwerte fuer Web Push (aktiv seit `v0.20-beta`):

```env
VAPID_SUBJECT=mailto:noreply@deine-domain.tld
VAPID_PUBLIC_KEY=...
VAPID_PRIVATE_KEY=...
```

Wichtig fuer Retry-Jobs in Produktion:

```env
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=database
# Optional bei stabiler Redis-Session-Infrastruktur:
# SESSION_DRIVER=redis
```

## 5) Erster Deploy

1. In Plesk Git auf `Deploy` klicken
2. Danach Seite im Browser öffnen
3. Login/Registrierung/Charakter-Erstellung testen

## 6) Zukünftiger Ablauf

Lokal:

```bash
git add .
git commit -m "Dein Change"
git push
```

Server:
- Entweder Auto-Deploy bei Push
- Oder in Plesk auf `Pull/Deploy` klicken
- Queue-Worker muss laufen (Scheduled Task/Prozess):

```bash
PHP_BIN=/opt/plesk/php/8.5/bin/php artisan queue:work --queue=default --tries=4 --sleep=1 --timeout=90
```

## 7) Troubleshooting (häufig)

- Fehler `500` direkt nach Deploy:
  - `.env` prüfen
  - `storage` und `bootstrap/cache` Rechte prüfen
  - Plesk PHP Error Log + `storage/logs/laravel.log` lesen

- DB-Fehler:
  - DB-Host/Name/User/Passwort in `.env` prüfen

- Assets/CSS fehlen:
  - Prüfen, ob `public/build` vorhanden ist
  - Falls nicht: lokal `npm run build`, committen, pushen

## 8) Sicherheit

- Niemals `.env` ins Repo committen
- Niemals GitHub Token teilen
- Für Produktivbetrieb `APP_DEBUG=false` lassen

## 9) Wichtiger PHP-Hinweis (Plesk)

- Das Projekt-Lockfile benoetigt PHP-CLI `>= 8.5`.
- Wenn der Server default `php` noch 8.3 ist, nutze in der Deploy Action:

```bash
PHP_BIN=/opt/plesk/php/8.5/bin/php /bin/bash scripts/plesk_post_deploy.sh
```
