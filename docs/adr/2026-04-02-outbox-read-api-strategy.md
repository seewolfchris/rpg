# ADR 2026-04-02: Outbox-/Read-API-Strategie ohne Big-Bang

## Status
Accepted (Spike)

## Kontext
Die Kernflüsse (Post-Store/Update/Moderation) laufen transaktional stabil.  
Benachrichtigungen sind bereits entkoppelt (Retry-Jobs), aber die Zustellpfade bleiben operativ sensibel:
- Fehler passieren nachgelagert und sind nicht immer über DB-Daten rekonstruierbar.
- Es gibt noch keine zentrale, domänenübergreifende Ereignisspur für späteres Read-API/Projection-Design.
- Vollständiges Event-Sourcing würde aktuell unnötig viel Komplexität einführen.

## Entscheidung
Kein Event-Sourcing-Big-Bang in v0.30.  
Wir fahren eine zweistufige, messgetriebene Strategie:

1. **Jetzt (Spike):**
   - Outbox-Kandidaten werden als strukturierte Events geloggt (`outbox.candidate`), optional via Feature-Flag.
   - Scope nur für Post-Notification-Fehlerpfade (`PostNotificationOrchestrator`).
   - Kein neues persistentes Outbox-Schema und keine neue Runtime-Abhängigkeit.

2. **Später (wenn Trigger erfüllt):**
   - Persistente Outbox-Tabelle für klar abgegrenzte Events (z. B. Notification-Intents).
   - Read-API nur für konkrete Hotpaths (z. B. Notification-Center/Timeline), nicht als generelles Parallelmodell.

## Trigger-Metriken für Ausbau auf persistente Outbox
Ausbau wird gestartet, wenn mindestens **ein** Trigger über 14 Tage stabil erfüllt ist:
- Retry-Job-Failures (`queue:failed`) > `0.5%` der Notification-Intents.
- P95 Retry-Latenz > `60s` zwischen Primär-Write und erfolgreicher Zustellung.
- Mehr als `2` produktive Incidents/Monat, bei denen Ursache ohne zusätzliche Logsuche nicht reproduzierbar ist.

## Read-API-Entscheidungsmatrix
- **Bleibt beim bestehenden Modell**, wenn:
  - P95 Seitenaufbau in Zielpfad <= 250 ms bleibt,
  - Query-Pläne stabil sind,
  - kein Incident-Druck aus Leserichtung besteht.
- **Read-API-Projektion nur für Hotpath**, wenn:
  - wiederholte Query-Regressionen trotz Index-/Cache-Tuning auftreten,
  - oder UX-Latenz in Kernansichten dauerhaft über Budget liegt.

## Konsequenzen
### Positiv
- Kein Over-Engineering bei aktuell stabilem Kern.
- Reale Telemetrie für spätere Architekturentscheidung.
- Geringes Risiko: Default bleibt aus, kein Verhaltensbruch.

### Negativ
- Noch keine harte Persistenzgarantie für Outbox-Ereignisse.
- Zusätzliche spätere Umsetzungsrunde bleibt notwendig.
