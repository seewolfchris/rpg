# Technische Schulden

Stand: 2026-07-11. Prioritaet bedeutet Risiko-/Wirkungsreihenfolge, nicht Release-Zusage.

## Hoch

- **TD-001 – MySQL-Concurrency-Gate:** Die 9 `mysql-concurrency`- und 5 `mysql-critical`-Tests
  sind lokal nicht gelaufen. Vor Release muessen sie in einer echten MySQL/MariaDB-Umgebung
  inklusive des verschaerften Moderations-/Punkte-Invariants gruen sein.
- **TD-002 – Datei-/DB-Transaktionsgrenze:** Media- und Handout-Dateioperationen koennen nicht
  atomar mit der relationalen Transaktion committen. Kompensation und Orphan-Audit existieren,
  ersetzen aber keinen expliziten, retry-faehigen Datei-Outbox-Workflow.
- **TD-003 – Kampf/Magie-Idempotenz:** Mehrschrittige Ressourcen-, Wurf- und Post-Mutationen
  benoetigen durchgaengige Row-Locks und Idempotency-Keys fuer Wiederholung/Parallelzugriff.

## Mittel

- **TD-004 – Notification-Durability:** Scene-Notifications besitzen ein Delivery-Ledger.
  Moderationsstatus- und Mention-Zustellung sind bei Prozessabbruch weiterhin at-least-once bzw.
  koennen nach Teilfehlern eine manuelle Nacharbeit benoetigen.
- **TD-005 – Read-Modell:** Der monotone `last_read_post_id`-Cursor ist race-sicherer geworden,
  modelliert aber Publikationszeit und nachtraegliche Freigaben nicht; siehe KI-001.
- **TD-006 – PWA-Datenzuordnung:** Auth-Boundary-Cleanup ist fail-closed, Queue-Datensaetze tragen
  aber keine eigene User-/World-Besitzkennung. Eine schemaeigene Zuordnung wuerde Recovery und
  forensische Pruefbarkeit verbessern.
- **TD-007 – Deployment-Atomizitaet:** `post_deploy.sh` prueft Security-Werte hart, deployt aber
  weiterhin in-place und startet Queue-Worker nicht kontrolliert neu. Zielbild: versionierte
  Release-Verzeichnisse, atomarer Symlink-Switch, `queue:restart` und Rollback.
- **TD-008 – Scope-Integritaet:** World-/Campaign-/Scene-Zugehoerigkeit wird an vielen Stellen
  in Policies/Actions validiert; relationale Composite-Constraints decken nicht alle
  Cross-Scope-Kombinationen ab.

## Niedrig

- **TD-009 – Listen/Bulk-Last:** Einzelne Admin-/Werkzeuglisten und Bulk-Pfade besitzen keine
  harte Obergrenze. Paginierung, Chunking und Request-Limits sollten vereinheitlicht werden.
- **TD-010 – Historische Doku:** Alte Audit-/Performance-Reports bleiben als Zeitaufnahme
  erhalten und duerfen nicht als aktueller Gate-Stand interpretiert werden; `docs/STATUS.md`
  bleibt kanonisch.
