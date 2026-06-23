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
- Offline-Lesen ist auf explizit vorgesehene Pfade begrenzt (Szenen-/Charakter-Ansichten).
- Private HTML-Responses bleiben standardmaessig `no-store/private`.
- Caching privater HTML-Responses ist nur mit explizitem serverseitigem Opt-in-Signal erlaubt.

## Offline-Post-Queue
- Persistenz in IndexedDB `chroniken-pbp`, Schema-Version `2`.
- Queue-Store `postQueue`: auto-inkrementierendes `id` sowie `url`, `method`, `entries`,
  `queued_at`, `source_path` und `source_url`. Retry-Felder wie `retry_count`,
  `next_retry_at`, `last_error_status` und `last_error_reason` werden bei Bedarf ergänzt.
- Dead-Letter-Store `postDeadLetters`: übernimmt den normalisierten Queue-Datensatz und
  ergänzt Fehlergrund, Status und Dead-Letter-Zeitpunkt.
- Migrationen erfolgen ausschließlich in `openQueueDatabase()` über
  `indexedDB.open(QUEUE_DB_NAME, <version>)` und `onupgradeneeded`. Bestehende Stores
  dürfen bei einem Versionswechsel nicht gelöscht werden; neue Felder bleiben optional,
  damit ältere Datensätze weiter gelesen werden können.
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
- Bei Logout und Auth-Boundary-Wechsel wird private Offline-Persistenz aktiv bereinigt.
- Ziel: keine persistente Uebernahme privater Daten zwischen Sessions/Users.

## Tests
- JS: `tests/js/sw.offline-queue.test.mjs`
- JS: `tests/js/post-probe-policy.test.mjs`
- JS: `tests/js/privacy-boundary.test.mjs`
- E2E: `tests/e2e/offline-auth-boundary.spec.mjs`
- E2E: `tests/e2e/offline-queue-retry.spec.mjs`
