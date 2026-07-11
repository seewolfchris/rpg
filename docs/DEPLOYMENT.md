# Deployment (Generisch)

Diese Anleitung ist provider-neutral und gilt für Linux-Hosts mit PHP 8.5+, Composer, MySQL/MariaDB und Redis.
Produktive Live-Instanz des Projekts: https://rpg.c76.org.
Release-, Entwicklungs-, Live-, Build- und Gate-Status werden kanonisch in `docs/STATUS.md` gepflegt.

## Zielbild Produktion

- Webroot zeigt auf `public/`.
- Datenbank: `DB_CONNECTION=mysql` (MySQL/MariaDB).
- Queue/Cache/Session: Redis.
- Queue-Worker läuft dauerhaft.
- Deploy-Checks laufen über `scripts/post_deploy.sh`.

## 1) Build und Commit lokal

```bash
composer install
npm install
npm run build
php artisan test
```

Der vollstaendige Release-/CI-Gate-Umfang steht in `docs/RELEASE-CHECKLISTE.md`;
dazu gehoeren Status-Drift, Architektur-Guardrails, E2E, Artisan-Smoke und
Build-Artefakt-Driftcheck.

## 2) Produktions-`.env` setzen

Nutze `.env.production.example` als Vorlage. Mindestwerte:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.org
APP_VERSION=
APP_BUILD=
WORLD_DEFAULT_SLUG=chroniken-der-asche

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=app
DB_USERNAME=app
DB_PASSWORD=secret

QUEUE_CONNECTION=redis
QUEUE_AFTER_COMMIT=true
CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true
TRUSTED_PROXIES=<proxy-ip/cidr,...>
SECURITY_HSTS_MAX_AGE=31536000
WEBPUSH_ENDPOINT_ALLOWED_HOSTS=fcm.googleapis.com,fcmregistrations.googleapis.com,*.push.services.mozilla.com,web.push.apple.com,*.web.push.apple.com
MEDIA_DISK=public
```

## 3) Deploy auf dem Zielhost

```bash
cd /var/www/<app>
PHP_BIN=php
COMPOSER_PATH=composer
/bin/bash scripts/post_deploy.sh
```

Hinweise:
- `PHP_BIN` und `COMPOSER_PATH` sind optional und können an das Zielsystem angepasst werden.
- Das Deploy-Skript fuehrt Guard-Checks aus und bricht insbesondere ab, wenn `APP_ENV`
  nicht `production`/`prod`, `APP_DEBUG` nicht `false`, `APP_URL` nicht HTTPS,
  Queue/Cache nicht Redis, Secure Cookie/Trusted Proxies nicht gesetzt, HSTS nicht positiv
  numerisch oder die Web-Push-Allowlist leer ist.

## 4) Queue-Worker starten

```bash
PHP_BIN=php
$PHP_BIN artisan queue:work --queue=default --tries=4 --sleep=1 --timeout=90
```

Empfohlen ist ein dauerhafter Prozessmanager (z. B. `systemd`, Supervisor oder Container-Orchestrierung).

## 5) Smoke nach Deploy

```bash
SMOKE_BASE_URL="https://example.org" SMOKE_WORLD_SLUG="<world-slug>" SMOKE_REPORT_OUT="docs/SMOKE-PASS-STAGING-PROD.md" scripts/release_smoke.sh
```

## 6) Troubleshooting (Kurz)

- `APP_KEY` fehlt/ungültig: in `.env` setzen, nicht automatisch pro Deploy rotieren.
- `QUEUE_CONNECTION` oder `CACHE_STORE` ungleich `redis`: auf Redis korrigieren.
- `APP_ENV`/`APP_DEBUG`/`APP_URL`, `SESSION_SECURE_COOKIE`, `TRUSTED_PROXIES`,
  `SECURITY_HSTS_MAX_AGE` und `WEBPUSH_ENDPOINT_ALLOWED_HOSTS` pruefen.
- Logs: `storage/logs/laravel.log` plus Host-Logs.
