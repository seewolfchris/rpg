# PWA / Offline (Detaildoku)

## Zweck
Technische Referenz fuer Service Worker, Offline-Lesen, Offline-Queue und Privacy-Boundary.

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
- Persistenz in IndexedDB (`chroniken-pbp`, Store `postQueue`).
- Dead-Letter-Store: `postDeadLetters`.
- Queue akzeptiert nur gleiche Origin und `POST`.
- Sensible Formkeys (`_token`, `password`, `*_token`, `csrf*`) werden vor Persistenz verworfen.

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
- JS: `tests/js/privacy-boundary.test.mjs`
- E2E: `tests/e2e/offline-auth-boundary.spec.mjs`
- E2E: `tests/e2e/offline-queue-retry.spec.mjs`
