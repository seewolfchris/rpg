# Immersion-Architektur (v0.28-beta)

Stand: 2026-04-02

## Zielbild

Die Immersion wird in kleinen, klar trennbaren Schichten umgesetzt:
- visuelle Atmosphäre und World-Theming
- narrativer Lesefluss im Thread
- narrative Feedbacks und Mikrointeraktionen
- offlinefähige "Brief"-Zustellung ohne Stilbruch

Wichtig: `resources/js/app.js` bleibt Orchestrator. Fachlogik für Immersion liegt in `resources/js/immersion/`.

## Rückblick Phasen 1-4

1. Phase 1 (Kategorie A) - Grundlagen und Lesefluss:
- World-Theme-Kontext via `WorldThemeResolver` + `config/world_themes.php`
- CSS-Variablen und `data-world-*` am Root
- Novel-Reading-Mode inkl. Fokus auf narrativen Thread

Gewinn:
- konsistenter Weltcharakter pro Request
- klar trennbarer UI- vs. Narrativ-Stil

2. Phase 2 (Kategorie B) - Narrative Interaktion:
- Submission-Feedback, Spoiler-Reveal, Revision-"Ink"
- Character-Dokument-Look und subtile Animationen

Gewinn:
- stärkere Rückmeldung ohne visuelles Overacting

3. Phase 3 (Kategorie C) - PWA/Offline-Immersion:
- Offline-Seite mit narrativem Kontext
- Queue als "Brief in Vorbereitung"
- Dead-Letter-Import in den Editor

Gewinn:
- stabile Offline-Nutzung ohne Bruch im Roman-Gefühl

4. Phase 4 (Kategorie D) - Bedienkonsistenz:
- Keyboard-Navigation (`N`/`P`/`Esc`) im Reading-Mode
- Focus-/Scroll-Polish, Bookmark-Fortschritt
- DE-first Terminologie in den zentralen UI-Texten

Gewinn:
- einheitlicher, vorhersehbarer Lesefluss auf Desktop und Mobile

## JS-Struktur (`resources/js/immersion/`)

- `reading-mode.js`
  - Thread-Lesemodus: Toggle, Fullscreen, `N`/`P`/`Esc`, Hash-Fokus, Reveal, Progress-Bookmark
  - kapselt bewusst alle `bindReading*`-Pfade

- `queue.js`
  - Offline-Queue, Status-Panel, Dead-Letter-Liste
  - Merge-Dialog für Entwurfsimport inkl. Focus-Trap
  - Service-Worker-Message-Handling für narrative Sync-Meldungen

- `utils.js`
  - gemeinsame Helfer für Overlay/Fokus, Storage und Sync-Notices
  - keine Businesslogik, nur wiederverwendbare UI/Runtime-Primitiven

## Regeln für neue Immersions-Features

1. Neue Reading-/Queue-/Keyboard-/narrative UI-Logik kommt in `resources/js/immersion/`, nicht in `app.js`.
2. `app.js` bleibt Einstiegspunkt (Import + Init), keine neuen Fachfunktionen dort.
3. Bestehende `data-*`-Attribute und World-CSS-Variablen wiederverwenden, nicht parallel neu erfinden.
4. Immer `prefers-reduced-motion` respektieren.
5. Mobile-first umsetzen und Offline/PWA-Flows nicht verschlechtern.
6. Keine neuen Dependencies für Immersion ohne explizite Architekturentscheidung.
7. Nach jeder Änderung: gezielte Regressionstests für Reading-Mode, Queue und JS laufen lassen.

## Kurz-Hinweise zu Kernbausteinen

### World-Theming
- Quelle: `config/world_themes.php` und `App\Support\WorldThemeResolver`
- Ausgabe: `data-world-slug`, `data-world-theme`, CSS-Variablen am Root (`html`/`body`)

### Reading-Mode
- Quelle: `resources/js/immersion/reading-mode.js`
- Grundlage: `data-scene-thread-reading-mode`, `data-reading-post-anchor`, Progress-Datenattribute
- Tastatur nur aktiv im Lesemodus und nur ohne offene Overlay-Dialoge

### Queue
- Quelle: `resources/js/immersion/queue.js`
- IndexedDB-Stores: `postQueue`, `postDeadLetters`
- SW-Ereignisse werden in narrative Statusmeldungen übersetzt, ohne Gamification-Noise

## Pflegehinweis

Diese Doku soll bewusst kurz bleiben. Wenn sie weiter wächst, ist das ein Signal für ein neues Unterdokument pro Themenbereich (Reading, Queue, Theming) statt eines neuen Monolithen.
