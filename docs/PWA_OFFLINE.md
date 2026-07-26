# PWA / Offline (Detaildoku)

## Zweck
Technische Referenz fuer Service Worker, Offline-Lesen, Offline-Queue und Privacy-Boundary.
Aktueller Build- und Beta-Status der Live-Instanz: siehe `STATUS.md`.

## Kernkomponenten
- Service Worker Einstieg: `public/sw.js`
- Runtime Core: `public/js/sw/runtime-core.js`
- Queue Runtime: `public/js/sw/runtime-queue.js`
- App Runtime: `resources/js/app/service-worker-runtime.js`
- Privacy-Boundary: `resources/js/app/privacy-boundary.js`
- Offline-Seite: `public/offline.html`

## Offline-Lesen
- Lokale Offline-Speicherung ist standardmaessig aus und muss pro Account ausdruecklich
  aktiviert werden.
- Offline-Lesen ist auf explizit vorgesehene Pfade begrenzt (Szenen-/Charakter-Ansichten).
- Private HTML-Responses bleiben standardmaessig `no-store/private`.
- Caching privater HTML-Responses ist nur mit explizitem serverseitigem Opt-in-Signal erlaubt.
- Bei deaktivierter Offline-Speicherung legt der Service Worker keine statischen, Seiten- oder
  Inhalts-Caches an. Beim Ausschalten werden alle `chroniken-*`-Caches geloescht.

## Offline-Post-Queue
- Persistenz in IndexedDB `chroniken-pbp`, Schema-Version `3`.
- Queue-Store `postQueue`: auto-inkrementierendes `id` sowie `url`, `method`, `entries`,
  `queued_at`, `source_path` und `source_url`. Retry-Felder wie `retry_count`,
  `next_retry_at`, `last_error_status` und `last_error_reason` werden bei Bedarf ergänzt.
- Jeder Queue- und Dead-Letter-Datensatz traegt `auth_boundary`, `user_id`, `world_slug`
  sowie – soweit aus der Route ableitbar – `campaign_id` und `scene_id`. Client und Service
  Worker lesen nur Datensaetze der aktuellen Auth-Boundary.
- Dead-Letter-Store `postDeadLetters`: übernimmt den normalisierten Queue-Datensatz und
  ergänzt Fehlergrund, Status und Dead-Letter-Zeitpunkt.
- Migrationen erfolgen ausschließlich in `openQueueDatabase()` über
  `indexedDB.open(QUEUE_DB_NAME, <version>)` und `onupgradeneeded`. Beim Wechsel von Version
  1/2 auf Version 3 werden alte, nicht sicher zuordenbare Queue- und Dead-Letter-Datensaetze
  einmalig verworfen. Spaetere Migrationen muessen Ownership und Datenminimierung erhalten.
- Queue akzeptiert nur gleiche Origin und `POST`.
- Sensible Formkeys (`_token`, `password`, `*_token`, `csrf*`) werden vor Persistenz verworfen.
- Aktivierte SL-Proben werden nie offline gespeichert. Der Vorabwurf-Token ist kurzlebig,
  einmalig und wird als `*_token` bewusst nicht persistiert. Das Formular bleibt bei einem
  Offline-Versuch unverändert geöffnet und muss online vorab gewürfelt und gesendet werden.

## Retry-/Fehlerverhalten
- `419`: Re-Signing-Versuch (neuer CSRF + aktuelle Form-Action), danach erneuter Sendeversuch.
- `401`/`419`/`429`: Eintrag bleibt in Queue, Retry mit Backoff (`retry_count`, `next_retry_at`).
- `4xx` (ausser `401`/`419`/`429`): Dead-Letter statt stilles Verwerfen.
- `5xx` oder Netzwerkfehler (`status=0`): bis zu 5 Retries, danach Dead-Letter.

## Relevante Service-Worker-Events
- `POST_SYNC_AUTH_RETRY`
- `POST_SYNC_DEAD_LETTERED`
- `POST_SYNC_RETRY_SCHEDULED`
- `POST_SYNC_AUTH_REQUIRED`
- `PRIVATE_DATA_CLEARED`

## Privacy-Boundary
- Bei Ausschalten, Logout und Auth-Boundary-Wechsel werden alle verwalteten Caches,
  Queue-Daten, lokalen Post-Entwuerfe und sitzungsbezogenen Welt-/Lesemoduswerte bereinigt.
- Weltkontext und Lesemodus liegen nur in `sessionStorage`; lokale Post-Entwuerfe werden
  ausschliesslich bei aktivierter Offline-Speicherung angelegt.
- HTMX-History und der HTMX-History-Cache sind deaktiviert.
- Ziel: keine persistente Uebernahme privater Daten zwischen Sessions/Users.
- Schlaegt die Boundary-Initialisierung fehl, werden Service Worker, Offline-Queue und
  Browser-Push nicht initialisiert. Sichere Navigation und normale Online-Formulare bleiben
  bedienbar; die UI zeigt einen zugaenglichen Fehlerhinweis.
- Der Logout entfernt die aktuelle Browser-Push-Subscription lokal und loest die
  serverseitige Endpoint-Zuordnung mit den Subscription-Credentials. Verknuepfte Geraete
  koennen in den Mitteilungs-Einstellungen einzeln oder gesammelt entfernt werden.

## Tests
- JS: `tests/js/sw.offline-queue.test.mjs`
- JS: `tests/js/post-probe-policy.test.mjs`
- JS: `tests/js/privacy-boundary.test.mjs`
- JS: `tests/js/browser-notifications-privacy.test.mjs`
- E2E: `tests/e2e/offline-auth-boundary.spec.mjs`
- E2E: `tests/e2e/offline-queue-retry.spec.mjs`
