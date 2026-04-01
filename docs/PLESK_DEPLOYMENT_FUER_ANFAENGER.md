# Plesk Deploy für Anfänger (Laravel RPG)

Diese Anleitung ist bewusst simpel. Arbeite sie von oben nach unten ab.

## 0) Was du wissen musst

- Lokal und in Produktion ist **MySQL/MariaDB** der Standard.
- CI-Tests laufen reproduzierbar mit **SQLite in-memory** (kein Produktiv-Setup).
- Du lädst **nicht einfach nur Dateien hoch und fertig**.
- Nach Upload müssen auf dem Server Laravel-Befehle laufen.

## 1) Lokal vorbereiten

Öffne in VS Code das Terminal (`Terminal -> New Terminal`) im Projektordner.

Führe aus:

```bash
composer install
npm install
npm run build
```

Optional (empfohlen):

```bash
php artisan test
```

## 2) Welche Dateien du hochladen sollst

Lade den **gesamten Projektordner** auf den Webspace, aber:

- `.env` lokal **nicht** hochladen (Server bekommt eigene `.env`).
- `node_modules` nicht nötig.
- `vendor` kann man hochladen, besser ist aber Composer auf Server laufen zu lassen.
- `public/hot` darf **nicht** auf Produktion aktiv sein.

Wenn `public/hot` existiert, lokal löschen:

```bash
rm -f public/hot
```

## 3) In Plesk: Datenbank anlegen

In Plesk:

1. `Websites & Domains`
2. `Databases`
3. `Add Database`
4. Notiere dir:
- DB-Name
- DB-User
- DB-Passwort
- Host (meist `localhost`)

## 4) In Plesk: Laravel Toolkit öffnen

1. `Websites & Domains`
2. `Laravel` (Toolkit)
3. Dein Projekt auswählen
4. Prüfen: **Document Root muss auf `public` zeigen**

## 5) Server-`.env` setzen

Nutze als Vorlage die Datei `.env.plesk.example` aus deinem Projekt.

Wichtige Werte in Plesk setzen:

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

Für Mail später SMTP eintragen. Zum Start kann Mail notfalls auf `log` bleiben.

Wichtig für Retry-/Notification-Stabilität:

```env
QUEUE_CONNECTION=database
```

Für Web Push (aktiv seit `v0.20-beta`) ergänzen:

```env
VAPID_SUBJECT=mailto:noreply@deine-domain.tld
VAPID_PUBLIC_KEY=...
VAPID_PRIVATE_KEY=...
```

## 6) Laravel-Kommandos auf dem Server ausführen

Im Laravel Toolkit oder über SSH im Projektordner:

```bash
PHP_BIN=/opt/plesk/php/8.5/bin/php
composer install --no-dev --optimize-autoloader
# APP_KEY muss in .env bereits gesetzt sein (nicht pro Deploy neu generieren):
grep '^APP_KEY=base64:' .env
$PHP_BIN artisan migrate --force
$PHP_BIN artisan storage:link
$PHP_BIN artisan optimize:clear
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache
```

Queue-Worker verbindlich starten (Plesk Scheduled Task oder dauerhafter Prozess):

```bash
PHP_BIN=/opt/plesk/php/8.5/bin/php
$PHP_BIN artisan queue:work --queue=default --tries=4 --sleep=1 --timeout=90
```

## 7) Dateirechte prüfen

Falls Uploads/Cache nicht funktionieren, in Plesk Dateimanager Rechte für folgende Ordner prüfen:

- `storage/`
- `bootstrap/cache/`

Beide müssen für den Webserver beschreibbar sein.

## 8) Erster Funktionstest

Öffne deine Domain und teste:

1. Landing Page lädt
2. Registrierung funktioniert
3. Login funktioniert
4. Charakter erstellen funktioniert
5. Bild-Upload funktioniert

## 9) Wenn Fehler kommen

Prüfe zuerst Logs:

- Plesk PHP Error Log
- Laravel Log: `storage/logs/laravel.log`

Typische Ursachen:

- falsche DB-Daten in `.env`
- `APP_KEY` fehlt
- `QUEUE_CONNECTION=sync` in Produktion oder kein Queue-Worker aktiv
- `public` nicht als Document Root
- fehlende Rechte auf `storage` / `bootstrap/cache`
- PHP-CLI zu alt (Projekt braucht `>= 8.5`)

Wenn PHP-CLI zu alt ist, Deploy-Script so starten:

```bash
PHP_BIN=/opt/plesk/php/8.5/bin/php /bin/bash scripts/plesk_post_deploy.sh
```

## 10) Lokal testen (ohne Upload)

Für Laravel immer so starten (nicht VSCode Live Server):

Terminal 1:

```bash
php artisan serve
```

Terminal 2:

```bash
npm run dev
```

Dann im Browser:

- `http://127.0.0.1:8000`

Live Server ist nur für statische HTML-Seiten sinnvoll, nicht für Laravel/PHP-Apps.
