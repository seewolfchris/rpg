# ADR 2026-03-08: Post-/Scene-Domäne in Services entkoppeln

## Status
Accepted

## Kontext
`PostController` und `SceneController` vereinten bis jetzt Orchestrierung, Geschäftslogik, Transaktionslogik, Notifications und Audit-Verhalten in großen Methodenblöcken.
Das erhöhte Änderungsrisiko, erschwerte gezieltes Testen und machte Produktanpassungen langsamer.

## Entscheidung
Wir verschieben Domänenlogik in klar getrennte Services und lassen Controller nur noch orchestrieren.

### Eingeführte Services
- `App\Domain\Post\StorePostService`
- `App\Domain\Post\PostProbeService`
- `App\Domain\Post\PostInventoryAwardService`
- `App\Domain\Post\PostModerationService`
- `App\Domain\Post\ScenePostNotificationService`
- `App\Actions\Post\UpdatePostAction` (Follow-up 2026-03-30, entkoppelt Post-Update-Write-Flow aus dem Controller)
- `App\Actions\Scene\BuildSceneThreadPageDataAction` (Follow-up 2026-03-30, entkoppelt Scene threadPage-Fragmentlogik)
- `App\Actions\Character\UpdateCharacterAction` (Follow-up 2026-03-30, entkoppelt Character-Update-Write-Flow aus dem Controller)
- `App\Actions\Character\UpdateCharacterInlineAction` (Follow-up 2026-03-30, entkoppelt Character-inlineUpdate-Flow inkl. HTMX-Response-Grenze)
- `App\Actions\Character\BuildCharacterShowDataAction` (Follow-up 2026-03-31, entkoppelt Character-Detaildaten-Aufbereitung aus dem Controller)
- `App\Actions\Character\BuildCharacterIndexDataAction` (Follow-up 2026-03-31, entkoppelt Character-Listenaufbereitung aus dem Controller)
- `App\Domain\Scene\SceneReadTrackingService`
- `App\Domain\Scene\ScenePostAnchorUrlService`
- `App\Domain\Scene\SceneInventoryQuickActionService`
- `App\Domain\Campaign\CampaignParticipantResolver`

## Transaktionsregeln
- Probe-Auflösung läuft in einer DB-Transaktion mit `lockForUpdate` auf dem Ziel-Charakter.
- Post-basierte Inventar-Awards laufen in einer DB-Transaktion mit `lockForUpdate`.
- Szenen-Inventar-Schnellaktionen laufen in einer DB-Transaktion mit `lockForUpdate`.
- Controller enthalten keine eigene Transaktionslogik mehr für diese Flows.

## Schnittstellenprinzip
- Öffentliche Web-Routen und Rollenlogik bleiben unverändert.
- Domain-Services werden per DI eingebunden.
- Controller liefern weiterhin dieselben Redirect-/Status-Responses.

## Konsequenzen
### Positiv
- Weniger Controller-Komplexität.
- Kleinere, fokussierte Services für Probe/Inventar/Moderation/Read-Tracking.
- Einfachere Erweiterbarkeit und bessere Lokalisierung von Fehlern.

### Negativ
- Mehr Klassen und höhere Strukturbreite.
- Saubere Service-Grenzen müssen künftig konsistent gehalten werden.
