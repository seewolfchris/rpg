# Operations Runbook

## Zweck
Schnelle Fehlersuche und reproduzierbare Reaktionen bei Incidents im laufenden Betrieb.

## Korrelation und Logs
- Jede Web-Response enthält `X-Request-Id`.
- Strukturierte Domänen-Logs schreiben Ereignisse mit `request_id`.
- Kritische Event-Typen:
  - `moderation.post_status_changed`
  - `probe.post_applied`
  - `inventory.post_award_applied`
  - `inventory.scene_quick_action_applied`
  - `post.created`

### Empfohlene Suchschlüssel
- `request_id`
- `user_id`
- `scene_id`
- `post_id`

## Standard-Incident-Ablauf
1. Fehlerbericht mit Zeitpunkt und betroffener Route aufnehmen.
2. `X-Request-Id` aus Response/Client-Log holen.
3. In Server-Logs nach `request_id` suchen und zusammenhängende Events prüfen.
4. Prüfen, ob Moderation/Probe/Inventar-Ereignisse vollständig vorhanden sind.
5. Falls Dateninkonsistenz: betroffene `post_id`, `scene_id`, `character_id` dokumentieren.

## Release Smoke
Verwende das Skript:

```bash
scripts/release_smoke.sh
```

Optional gegen laufende Instanz:

```bash
SMOKE_START_SERVER=0 SMOKE_BASE_URL="https://example.org" scripts/release_smoke.sh
```

## Nach Deployment
1. `scripts/release_smoke.sh` ausführen.
2. Dashboard, Szene und GM-Moderation manuell öffnen.
3. Bei Fehlern `request_id` notieren und Incident-Ablauf starten.
