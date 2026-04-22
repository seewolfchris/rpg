# ROADMAP - C76-RPG (Strategie)

Aktueller Live-Status (Version, Gate-Stand, letzter Release): siehe `docs/STATUS.md`.

## Zielbild

- Stabile, wartbare Release-Beta mit verlässlicher Delivery.
- Plattform bleibt multi-world-fähig mit harten Weltkontext-Invarianten.
- Qualitätsgates bleiben strikt und werden nicht aufgeweicht.
- Architektur wird gezielt vereinfacht: weniger Drift, weniger Wrapper, klarere Verantwortungen.

## Leitplanken

- Keine Feature-Expansion im Rahmen der Konsolidierungsschnitte.
- Keine Lockerung von CI, Analyse, Architektur-Guardrails, E2E oder MySQL-Concurrency/Critical.
- Keine stillen Änderungen an Route-Namen, URIs, Middleware oder Response-Verträgen.
- Release-/CI-Befehle bleiben kanonisch in `docs/RELEASE-CHECKLISTE.md`.
- WIP-Limit bleibt: `1 Feature-Task + 1 Bugfix`.

## Strategische Arbeitspakete (laufend)

1. **Dokumentations- und Konfigurationsdrift abbauen**
   - Eine kanonische Statusquelle verwenden, doppelte Live-Wahrheiten entfernen.
2. **Action-Layer verschärfen**
   - Nur Actions mit echtem Orchestrationswert behalten; dünne Wrapper abbauen.
3. **Routing nach Kontexten weiter schneiden**
   - Welt-Routing organisatorisch entflechten, bei unveränderten öffentlichen Verträgen.
4. **Deploy-/Queue-Policy vereinheitlichen**
   - Produktionsstandard Redis konsistent in Vorlagen, Runbooks und Guards durchziehen.
5. **Historische Betriebsartefakte aus dem Standardpfad entfernen**
   - Aktive Konfiguration von Archivmaterial trennen.
6. **Post-Domäne phasenorientiert strukturieren**
   - Store/Update-Flows in klare Phasen gliedern, ohne Verhaltensänderung.

## Priorisierung

1. Drift entfernen (Doku + Konfig).
2. Dünne Wrapper-Actions zusammenziehen.
3. `routes/web/world.php` weiter nach Kontexten splitten.
4. Redis-Produktionsstandard überall konsistent machen.
5. Historische Artefakte bereinigen/archivieren.
6. Danach den Post-Hotspot tiefer phasenorientiert refactoren.

## Nicht-Ziele der Roadmap

- Kein Realtime-/WebSocket-Produktkern als Standard.
- Keine SPA-First-Neuausrichtung.
- Keine externe Medien-CDN-Optimierung als Pflichtpfad.
