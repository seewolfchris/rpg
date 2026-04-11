# Content-Plan C76-RPG

Stand: 2026-04-04 (v0.28-beta)

## 1. Projekt-Definition (kurz, spielbar)

C76-RPG ist ein asynchrones Play-by-Post-RPG mit Multi-World-Struktur und klarem Immersions-Fokus.  
Das Kernsystem ist ein d100-Light-Ansatz: Proben sind seltene, gezielte Scharniere und werden durch GM/Co-GM ausgelöst.  
Der Spielfluss lebt von Szene, Reaktion, Konsequenz statt von Zahlenverwaltung.

## 2. Zielgruppen & Inhalts-Bedarf

### 2.1 Anfänger

Brauchen einen klaren Einstieg ohne Regelwand:

- Charakter erstellen (minimal spielbar).
- Erste Szene posten (IC/OOC sauber trennen).
- Verstehen, wie Proben und Moderation wirklich laufen.
- Kurz wissen, was Abkürzungen bedeuten.

### 2.2 Fortgeschrittene / GM

Brauchen operative Tiefe:

- Kampagnenführung im asynchronen Tempo.
- Probe-Trigger, Modifikatoren, Konsequenzspiel.
- Moderations-Workflows (`pending`, `approved`, `rejected`).
- Strukturierte Pflege von Enzyklopädie-Inhalten je Welt.

### 2.3 Allgemein

Pflicht-Content für Nutzbarkeit:

- Globales Regelwerk (spielbar, knapp, eindeutig).
- Glossar + Abkürzungen.
- Weltbeschreibungen und Lore-Bloecke.
- Ein Beginner-Guide, der wirklich in die erste Szene bringt.

## 3. Content-Architektur im Repo (minimal-invasiv)

### 3.1 Ablage

- Globales Regelwerk: `docs/content/global/*.md`
- Weltlore (ab Phase 2): `docs/content/worlds/{world-slug}/lore/**/*.md`
- Bildassets: `public/images/lore/{world-slug}/...`

### 3.2 Integration ins Wissenszentrum

Phase 1 bleibt bewusst schlank:

- Genau drei feste Markdown-Dateien:
  - `grundregeln.md`
  - `glossar.md`
  - `abkuerzungen.md`
- `KnowledgeController::rules()` lädt diese Dateien direkt.
- `rules.blade.php` rendert nur noch HTML aus Markdown.

### 3.3 Enzyklopädie für Lore

Die Enzyklopädie bleibt in Phase 1 unverändert als bestehender Lore-Kanal.  
Markdown->DB-Sync kommt erst in Phase 2, wenn Lore-Dateien stabil und testbar strukturiert sind.

### 3.4 Tracking

- `docs/CONTENT-PLAN.md` bleibt die verbindliche Leitdatei.
- `docs/CONTENT-STATUS.md` (folgt) trackt Status pro Artefakt (todo/in progress/done/review).

## 4. Priorisierter Umsetzungs-Plan (Phasen mit Aufwand & Abhängigkeiten)

### Phase 1 (2-3 PT, strikt schlank)

Glossar + Grundregeln + Abkürzungen (DE-first), aus genau drei festen Markdown-Dateien
in `/wissen/regelwerk` rendern.

Harte Grenzen in Phase 1:

- kein `content:sync` Command
- kein Frontmatter-Parsing im Runtime-Read
- keine neue Repository-Klasse

Abhängigkeiten:

- keine

Abnahme:

- `/wissen/regelwerk` zeigt Markdown-Inhalte.
- Keine harten Regeltexte mehr in `rules.blade.php`.
- Bestehende Knowledge-Tests bleiben grün (oder kontrolliert angepasst).

### Phase 2 (4-6 PT)

Weltlore für `chroniken-der-asche` konsolidieren, Markdown-Quelle definieren und erst dann Sync bauen.

Abnahme:

- Lore-Dateien strukturiert unter `docs/content/worlds/chroniken-der-asche/lore/`.
- `content:sync` v1 einsatzfähig.
- Enzyklopädie-Tests laufen fixture-basiert statt seed-implizit.

### Pflicht-Schritt vor echter Lore-Sync-Einführung (Seed-Migrations-Bereinigung)

Hart:

- Legacy-Lore aus alten Migrations darf nicht langfristige Content-Quelle bleiben.

Konkrete Schritte:

1. Seed-Lore-Migrations inventarisieren (Dateiliste + Slugliste).
2. Test-Fixture von Seed-Inhalten entkoppeln (eigene Testdaten statt impliziter Migrationstexte).
3. Legacy-Lore per gezielter Cleanup-Migration oder One-Off-Skript aus DB entfernen/deaktivieren.
4. Danach erst Markdown->DB-Sync für Lore aktivieren.

Inventar (aktuell):

- `2026_03_03_093734_create_encyclopedia_categories_table.php`
- `2026_03_03_093734_create_encyclopedia_entries_table.php`
- `2026_03_05_191000_add_species_lore_entries_to_encyclopedia.php`
- `2026_03_06_090000_add_magic_and_liturgy_lore_entries.php`
- `2026_03_06_103000_add_monster_bestiary_lore_entries.php`
- `2026_03_06_113000_add_regions_and_cities_lore_entries.php`
- `2026_03_06_123000_add_weapons_armor_relics_lore_entries.php`
- `2026_03_06_133000_add_hero_archetypes_lore_entries.php`

Brutale Realität:

- Wenn du Seed-Lore jetzt sofort hart entfernst, brechen Enzyklopädie-Feature-Tests ohne Ersatz-Fixture.

### Phase 3 (2-3 PT)

Beginner-Guide + "Wie spielt man als Fortgeschrittener?" mit klaren Spielablauf-Beispielen.

### Phase 4 (variabel)

Weltspezifische Regeln + weitere Welten.  
Nur starten, wenn Phase 2 stabil und testbar ist.

### Phase 5 (2-4 PT)

Integration ins Wissenszentrum mit besserer visueller Aufbereitung (Cards, Bilder, Reading-Mode).

## 5. Wartbarkeits-Regeln (hart)

### 5.1 Grundregeln

1. Inhalte in Markdown pflegen, nicht in Blade-Strings.
2. Globales Wissen und Weltlore klar trennen.
3. Keine versteckten Content-Quellen in Migrations.
4. Jede größere Inhaltsänderung bekommt Release-Bezug (z. B. `v0.26`).
5. DE-first, konsistente Begriffe über alle Seiten.
6. Bilder nur mit Alt-Text.
7. Keine neuen content-lastigen DB-Migrations für laufende Redaktion.

### 5.2 Immersions-Stil-Guide (hart)

1. Kurze, atmosphärische Saetze.
2. Ich-Perspektive, wo sie den Lesefluss stärkt.
3. Keine trockene Verwaltungssprache.
4. Keine Formulierungen wie "Der Spieler wuerfelt ...".
5. Stattdessen handlungsnah: "Du setzt an ...", "Der GM loest die Probe aus ...".
6. Regeln bleiben klar, aber klingen wie Welttext, nicht wie Formular.
7. OOC-Hinweise kurz und sichtbar getrennt.

### 5.3 Markdown->DB-Sync Spezifikation (erst Phase 2)

Datei-Kontrakt für Weltlore:

- Pfad: `docs/content/worlds/{world-slug}/lore/**.md`
- Frontmatter-Mindestfelder:
  - `title`
  - `slug`
  - `category`
  - optional: `excerpt`, `status`, `position`, `published_at`

Geplanter Command (Phase 2):

- `php artisan content:sync`
- Optionen:
  - `--world=chroniken-der-asche`
  - `--dry-run`
  - `--force`

Geplante Verarbeitung (Phase 2):

1. Markdown-Dateien einlesen.
2. Frontmatter validieren.
3. Markdown via `Str::markdown()` in rendertes Content-HTML überführen.
4. In Kategorien/Einträge upserten.
5. Bei `--dry-run` nur Diff/Statistik ausgeben.
6. Bei `--force` Konfliktchecks lockern (trotzdem loggen).
