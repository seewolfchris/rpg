# Operations Runbook

## Zweck
Schnelle Fehlersuche und reproduzierbare Reaktionen bei Incidents im laufenden Betrieb.
Security-Header werden zentral in `App\Http\Middleware\ApplySecurityHeaders` gesetzt.

## Verbindliche Produktions-Defaults (Security/Betrieb)
- `QUEUE_CONNECTION=redis`.
- `CACHE_STORE=redis`.
- `SESSION_DRIVER=redis`.
- `SESSION_SECURE_COOKIE=true` (alternativ Key entfernen, dann greift produktiver Secure-Fallback).
- `QUEUE_AFTER_COMMIT=true` fuer commit-sicheres Dispatching asynchroner Jobs.
- `TRUSTED_PROXIES=<proxy-ip/cidr,...>` (oder `*` nur bei voll vertrauenswuerdiger Proxy-Kette).
- `SECURITY_HSTS_MAX_AGE > 0` (empfohlen `31536000`).

Schnellcheck auf dem Zielhost:

```bash
cd /var/www/vhosts/c76.org/rpg.c76.org
grep -E '^(APP_ENV|SESSION_SECURE_COOKIE|QUEUE_CONNECTION|QUEUE_AFTER_COMMIT|TRUSTED_PROXIES|SECURITY_HSTS_MAX_AGE)=' .env
```

## Korrelation und Logs
- Jede Web-Response enthält `X-Request-Id`.
- Strukturierte Domänen-Logs schreiben Ereignisse mit `request_id`.
- Kritische Event-Typen:
  - `moderation.post_status_changed`
  - `probe.post_applied`
  - `inventory.post_award_applied`
  - `inventory.scene_quick_action_applied`
  - `post.created`
  - `post.scene_notifications_failed`
  - `post.mention_notifications_failed`
  - `post.scene_notifications_retry_succeeded`
  - `post.mention_notifications_retry_succeeded`
  - `post.scene_notifications_retry_exhausted`
  - `post.mention_notifications_retry_exhausted`
  - `outbox.candidate` (nur wenn `OUTBOX_SPIKE_LOG_CANDIDATES=true`)
  - `webpush.subscription_upserted`
  - `webpush.subscription_deleted`
  - `webpush.scene_post_sent`
  - `webpush.delivery_failed`

### Empfohlene Suchschlüssel
- `request_id`
- `user_id`
- `scene_id`
- `post_id`

## Web Push Schnellcheck
Wenn Browser-Push nicht ankommt, zuerst:

```bash
php artisan tinker --execute="echo config('webpush.vapid.public_key') ? 'VAPID OK'.PHP_EOL : 'VAPID MISSING'.PHP_EOL;"
php artisan tinker --execute="echo \App\Models\PushSubscription::count().PHP_EOL;"
```

Dann in Logs nach WebPush-Events suchen:

```bash
grep -E "webpush\\.(subscription_upserted|subscription_deleted|scene_post_sent|delivery_failed)" storage/logs/laravel.log | tail -n 100
```

Hinweis:
- `webpush.delivery_failed` mit Status `404` oder `410` führt zur automatischen Löschung der ungültigen Subscription.

## Notification-Retry-Queue Schnellcheck
Wenn Szenen-/Mention-Benachrichtigungen ausfallen:

0. `.env` prüfen:
   - `QUEUE_CONNECTION=redis`
   - `QUEUE_AFTER_COMMIT=true`
   - `CACHE_STORE=redis`
   - `SESSION_DRIVER=redis`
1. Queue-Worker prüfen (redis-queue):
   - `php artisan queue:work --queue=default --tries=4 --sleep=1 --timeout=90`
2. Fehlgeschlagene Jobs prüfen:
   - `php artisan queue:failed`
3. Falls nötig erneut anstossen:
   - `php artisan queue:retry all`

Log-Hinweise:
- Erstfehler im Request: `post.scene_notifications_failed` / `post.mention_notifications_failed`
- Erfolgreicher Retry: `post.*_retry_succeeded`
- Endgültig ausgeschöpft: `post.*_retry_exhausted`
- Optionaler Outbox-Spike: `outbox.candidate` (nur bei aktivem Flag)

## Offline-Post-Queue Schnellcheck
Wenn Offline-Posts nicht synchronisiert werden:

Offline-Modus & PWA: Ungesendete Posts werden lokal im Browser (IndexedDB) gespeichert, damit du auch ohne Internetverbindung schreiben kannst. Auf geteilten Geräten, bei Browser-Export oder Kompromittierung des Geräts können andere Personen diese Inhalte lesen. Bei Logout werden alle privaten Caches und die Offline-Queue automatisch gelöscht. Du kannst die Offline-Queue in den Einstellungen jederzeit deaktivieren.

1. Browser-Konsole auf Service-Worker-Events prüfen (`POST_SYNC_*`).
2. IndexedDB `chroniken-pbp` / Store `postQueue` prüfen (`retry_count`, `next_retry_at`, `last_error_status`).
3. Sicherstellen, dass Queue-Einträge keine sensiblen Keys enthalten (`_token`, `password`, `*_token`, `csrf*`).
4. Sicherstellen, dass Queue-Ziele nur gleiche Origin und `POST` sind (keine Fremd-Hosts, keine GET/PUT/DELETE-Syncs).
5. Bei `POST_SYNC_AUTH_REQUIRED`: Login-Status prüfen, Seite neu laden, Sync erneut anstoßen.

Erwartete Event-Reihenfolgen:
- 419 mit erfolgreichem Re-Signing:
  - `POST_SYNC_STARTED`
  - `POST_SYNC_AUTH_RETRY`
  - `POST_SYNC_SUCCESS`
  - `POST_SYNC_FINISHED`
  - Hinweis: CSRF wird nur transient für den Retry gesetzt und nicht in IndexedDB persisted.
- Session/CSRF nicht erneuerbar:
  - `POST_SYNC_STARTED`
  - `POST_SYNC_RETRY_SCHEDULED`
  - `POST_SYNC_AUTH_REQUIRED`
  - `POST_SYNC_FINISHED` (mit `remaining > 0`)

## Lokaler Testlauf zeigt viele 419-Fehler
Typisches Symptom:
- plötzlich schlagen viele POST/PATCH/DELETE-Feature-Tests mit `419` fehl.

Häufige Ursache:
- gecachte lokale Runtime-Konfiguration (`config/routes/events/views`) wurde nicht vor dem Testlauf geleert.

Sofortmaßnahme:

```bash
php artisan optimize:clear
php artisan test --without-tty --do-not-cache-result
```

## Standard-Incident-Ablauf
1. Fehlerbericht mit Zeitpunkt und betroffener Route aufnehmen.
2. `X-Request-Id` aus Response/Client-Log holen.
3. In Server-Logs nach `request_id` suchen und zusammenhängende Events prüfen.
4. Prüfen, ob Moderation/Probe/Inventar-Ereignisse vollständig vorhanden sind.
5. Falls Dateninkonsistenz: betroffene `post_id`, `scene_id`, `character_id` dokumentieren.

## Browser-Kompromittierung / Geräte-Teilung
Bei Incident ‚Browser-Kompromittierung‘ oder Verdacht auf Geräte-Teilung: User anweisen, im Browser ‚IndexedDB löschen‘ (Entwicklertools → Application → IndexedDB → chroniken-pbp) oder die Seite im Inkognito-Modus neu zu laden. Danach Logout erzwingen.

## Release Smoke
Verwende das Skript:

```bash
scripts/release_smoke.sh
```

Optional gegen laufende Instanz:

```bash
SMOKE_BASE_URL="https://example.org" SMOKE_WORLD_SLUG="<world-slug>" SMOKE_REPORT_OUT="docs/SMOKE-PASS-STAGING-PROD.md" scripts/release_smoke.sh
```

Hinweise:
- Wenn `SMOKE_WORLD_SLUG` fehlt, nutzt das Skript `WORLD_DEFAULT_SLUG` aus `.env`.
- Bei externer `SMOKE_BASE_URL` wird kein lokaler `artisan serve` gestartet.

Optional ohne HTTP-Checks (z. B. CI/offline):

```bash
SMOKE_MODE=artisan SMOKE_REPORT_OUT="docs/SMOKE-PASS-LOCAL.md" scripts/release_smoke.sh
```

## Testflight-Seed für manuelle QA
- Zweck: reproduzierbare QA-Kampagne mit definierter Rollen-/Einladungsmatrix.
- Command:

```bash
php artisan dev:testflight:seed --world=<world-slug> --password='<starkes-passwort>'
```

- Ohne `--password` wird pro Lauf ein zufälliges Passwort generiert und im Output ausgegeben.
- Der Command ist in `production` hart blockiert (kein Bypass).
- Default-Campaign-Slug: `testflight-<world-slug>-qa` (Testflight-kennzeichnete Daten).

## Nach Deployment
1. `scripts/release_smoke.sh` ausführen.
2. Dashboard, Szene und GM-Moderation manuell öffnen.
3. Bei Fehlern `request_id` notieren und Incident-Ablauf starten.

## Release-Vorbereitung
1. Siehe `README.md` (Dokumentationssektion) fuer den aktuellen Standard-Flow-Link.
2. Standard-Aufruf:
   - `scripts/release_flow.sh vX.Y-beta --world <slug> --archive`
3. Varianten:
   - Dry-Run: `scripts/release_flow.sh vX.Y-beta --dry-run --iter 500 --archive`
   - Ohne Perf-Gate: `scripts/release_flow.sh vX.Y-beta --skip-perf`
4. Perf-Gate Verhalten:
   - `ROT` ist report-only, wenn Enforce aus ist (`PERF_GATE_ENFORCE=0`).
   - Mit Enforce (`PERF_GATE_ENFORCE=1`, Standard für stabile `vX.Y`-Tags im `release_flow.sh`) endet `ROT` als non-zero.
   - Runtime-Hint wird nicht automatisch in `.env` geschrieben.
   - Hint-Entscheidung kommt aus `docs/PERFORMANCE-POSTS-LATEST-BY-ID-GATE-LATEST.md`.

## Phase-A Stabilitätschecks (historisch, archiviert)
- Archivpfad: `ops/archive/release_phase_a/`
- `ops/archive/release_phase_a/release_phase_a_stability_check.sh` benötigt `node` (JS-Draft-Tests).
- Falls Zielhost kein Node installiert hat:
  - Stabilitätscheck lokal oder in CI ausführen.
  - Auf dem Zielhost nur `ops/archive/release_phase_a/release_phase_a_smoke.sh` als laufende Betriebsprüfung nutzen.
