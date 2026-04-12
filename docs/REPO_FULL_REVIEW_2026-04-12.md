# Full Repository Review – 2026-04-12

## Gesamturteil
Das Repository ist funktional weit und testseitig breit abgesichert, aber ohne Nachschaerfung nicht in allen Betriebs-/Security-Defaults produktionshart.

## Top-Risiken (Kurzliste)
1. Unsicherer Session-Cookie-Default in Produktivumgebungen (`SESSION_SECURE_COOKIE=false` in bisherigen Defaults).
2. Queue-Dispatch nicht commit-sicher als Standard (`after_commit=false` in async Queues).
3. Offline-Privacy-Boundary war nur best effort (nicht sessiongebunden).
4. Invitation-Response (`accept/decline`) war nicht atomar gegen Parallelzugriffe.
5. OOC-Regel war zwischen Store/Update inkonsistent.
6. Read-Tracking-Drift bei eigenem Posting mit bestehender Subscription.
7. Redundante World-Existenz-Queries pro Request.
8. Mehrfachzaehlungen in Notification-/Subscription-Ansichten mit unnoetigem Query-Overhead.
9. Betriebsleitfaden ohne verbindliche Security-Defaults fuer Deploy-Checks.
10. Dokumentationsstand nicht ueberall synchron auf denselben Verifikationszeitpunkt.

## Dateibezogene Findings
Die technischen Findings und Priorisierungen sind im Zuge des Stabilisierungspakets in folgende Schwerpunkte aufgeteilt:
- `config/session.php`, `.env.example`, `scripts/plesk_post_deploy.sh`
- `config/queue.php`
- `config/trustedproxy.php`, `bootstrap/app.php`, `app/Http/Middleware/ApplySecurityHeaders.php`
- `app/Http/Controllers/CampaignInvitationController.php`
- `app/Http/Requests/Post/StorePostRequest.php`, `app/Http/Requests/Post/UpdatePostRequest.php`
- `app/Domain/Post/StorePostService.php`
- `resources/js/app/privacy-boundary.js`, `resources/js/app/service-worker-runtime.js`, `public/js/sw/runtime-core.js`
- `app/Http/Middleware/ApplyWorldContext.php`
- `app/Http/Controllers/NotificationController.php`, `app/Http/Controllers/SceneSubscriptionController.php`
- ergaenzende Feature-/MySQL-Concurrency-/E2E-Tests

## Priorisierte Massnahmen

### P0 – Sofort beheben
- Secure-by-default Session-/Queue-/Proxy-Konfiguration.
- Harte Deploy-Guards fuer unsichere Produktivwerte.
- Atomare Invitation-Responses (`lockForUpdate` + Pending-only Transition).
- OOC-Regelparitaet fuer Store/Update (Moderatoren-Ausnahme).
- Korrektur der Read-Pointer-Aktualisierung bei eigenem Posting.

### P1 – Vor naechstem Release
- Sessiongebundene Offline-Privacy-Boundary inkl. Service-Worker-Cache-Namespace.
- Reduktion redundanter World-Context-Queries.
- Aggregierte Zaehllogik fuer Notification-/Subscription-Ansichten.

### P2 – Mittelfristig / Dokumentation
- Einheitliche Verifikationsbloecke (Datum, Kommandos, Ergebnis).
- Verbindliche Produktions-Defaults zentral in Runbook/Release-Checkliste.

## Fehlende Tests (priorisiert)
- MySQL-Concurrency-Test fuer paralleles `accept/decline` derselben Einladung.
- Feature-Test fuer Pending-only Invitation-Transition bei mehrfacher Response.
- Feature-Test fuer OOC-Regelparitaet (Spieler blockiert, Moderatoren erlaubt).
- Feature-Test fuer Read-Tracking bei bestehender Subscription und eigenem neuen Post.
- E2E fuer sessiongebundene Offline-Privacy-Boundary.

## Verifikationsstand
- Stand dieses Dokuments: 2026-04-12.
- Abgleich erfolgt mit dem Stabilisierungspaket (`Security + Korrektheit + Performance`).
