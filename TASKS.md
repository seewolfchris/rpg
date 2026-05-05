# TASKS: Kampf- und Magiesystem

## 1. Zielbild
Das Kampf- und Magiesystem für C76-RPG bleibt story-first und Play-by-Post-kompatibel. Spieler formulieren Absichten als normale IC-Beiträge im Szenenthread. Spielleitung und Co-Spielleitung werten diese Absichten strukturiert aus und erzeugen nachvollziehbare Ergebnisse mit d100-Logik und klaren Konsequenzen auf Werte.

## 2. Leitplanken
- Play-by-Post zuerst, keine Echtzeitpflicht.
- Spielleitung und Co-Spielleitung werten aus.
- Spieler schreiben Absichten im normalen Thread.
- d100 bleibt Grundlage: `Wurf + Modifikator <= Zielwert`.
- Keine Initiative-Engine in V1.
- Keine WebSockets.
- Feature-Flag für neue Werkzeuge: `features.combat_tools_enabled`.
- Keine Spieler-Kampfqueue in V1.
- Kein Spielerformular für Kampfaktionen in V1.
- Bestehender IC/OOC-Postingfluss bleibt unverändert.
- Bestehende Einzel-Proben bleiben unverändert.

## 3. PR-0 — ADR + Plan

### Aufgaben
- ADR als belastbares Entscheidungsdokument erstellen oder aktualisieren.
- TASKS-Plan PR-0 bis PR-6 mit klaren Schnittstellen und Grenzen erstellen.
- Terminologie in sichtbaren Texten konsistent halten: `Spielleitung`, `Co-Spielleitung`, `Spieler`.

### Akzeptanzkriterien
- ADR enthält Status, Kontext, Entscheidung, Attributmodell, Kampf-V1, NPC-Strategie, Magie-Zielbild, Feature-Flag, Konsequenzen, Non-Goals, Roadmap, Verifikation.
- TASKS enthält pro PR konkrete Aufgaben, Akzeptanzkriterien, Nicht-tun-Liste, Verifikation.
- In beiden Dokumenten steht ausdrücklich:
  - kein Spielerformular für Kampfaktionen in V1
  - normale IC-Posts bleiben der Ort für Spielerabsichten

### Nicht tun
- Keine Migrationen.
- Keine Models.
- Keine Controller.
- Keine Requests.
- Keine Routen.
- Keine Views.
- Keine Tests.
- Keine Runtime-Änderungen.

### Verifikation
- Markdown-Struktur und Lesbarkeit prüfen.
- Optionalen Konsistenzcheck gegen bestehende ADR-Stile durchführen.
- Kein technischer Testlauf erforderlich.

## 4. PR-1 — Attribut-current-Fundament

### Aufgaben
- Datenbank-Erweiterung nur für aktuelle Attributwerte planen und umsetzen:
  - `mu_current`, `kl_current`, `in_current`, `ch_current`, `ff_current`, `ge_current`, `ko_current`, `kk_current`
- Backfill-Strategie: aktuelle Werte initial aus bestehenden kanonischen Attributwerten setzen.
- Character-Model für neue `*_current` Felder in Casts/Fillable ergänzen.
- Charakterbogenanzeige auf `Max / Aktuell` vorbereiten:
  - Maximalwert aus bestehendem Attribut (`mu` etc.)
  - aktueller Wert aus `*_current`
- Clamping-Regeln einführen:
  - `current` nie über effektivem Maximalwert
  - `current` nie unter gültiger Untergrenze
- LE/AE-Muster als Referenzlogik verwenden.

### Akzeptanzkriterien
- Kanonische Attributspalten `mu..kk` bleiben unverändert Quelle für Grund-/Maximalwerte.
- Es gibt keine neuen `*_max` Spalten für Attribute.
- Charakterbogen zeigt technisch korrekt `Max / Aktuell`.
- `*_current` wird beim Lesen/Schreiben sauber geclamped.
- Bestehende Charakterfunktionen bleiben nutzbar.

### Nicht tun
- Kein Kampfservice.
- Keine Kampf-UI.
- Keine Magieauswertung.
- Keine Kampfphasen-Tabellen.

### Verifikation
- Fokussierte Unit-Tests für Clamping und Attributmodell.
- Fokussierte Feature-Tests für Character-Ansicht.
- Relevante statische Analyse ausführen.

## 5. PR-2 — CombatService MVP

### Aufgaben
- `CombatService` für einzelne Kampfaktion implementieren.
- Bestehende d100-/ProbeLogik wiederverwenden (`ProbeRoller` bzw. bestehende Muster).
- Einzelaktionsauswertung umfasst:
  - Angriffswert
  - Verteidigung
  - Schaden
  - Rüstungsschutz
  - Modifikator
- Zieltyp `character`:
  - LE korrekt aktualisieren
  - Grenzen und Clamping beachten
- Zieltyp `npc`:
  - keine Character-Mutation
  - Ergebnis rein als Snapshot-/Log-Resultat erzeugen
- Ergebnis als klares DTO zurückgeben.
- Invarianten für World-Context, Rollen und Teilnehmer konsistent zu bestehenden Domain-Services ausführen.

### Akzeptanzkriterien
- Eine einzelne Kampfaktion ist deterministisch auswertbar.
- Ergebnis-DTO enthält mindestens Wurf, Zielwert, Erfolg, angewendete Auswirkungen, Kontext.
- Character-Ziele werden korrekt und begrenzt aktualisiert.
- NPC-Ziele erzeugen konsistente Snapshot-Ergebnisse ohne Zwang zu NPC-Modell.
- Fehlerfälle liefern nachvollziehbare Domain-/Validierungsfehler.

### Nicht tun
- Keine große Szenen-UI.
- Keine Spieler-Kampfformulare.
- Keine Spielerqueue.
- Keine Mehrfachaktions-Kampfphase.
- Keine Initiative-Engine.

### Verifikation
- Fokussierte Unit-Tests auf Service-Ebene.
- Fokussierte Feature-Tests für erlaubte/unerlaubte Rollen- und Context-Pfade.
- Analyse und relevante Testfilter ausführen.

## 6. PR-3 — SL-only Kampfaktions-UI

### Aufgaben
- UI hinter `features.combat_tools_enabled` aktivierbar machen.
- In Szenenansicht einen Bereich nur für Spielleitung und Co-Spielleitung bereitstellen.
- Formular für einzelne Kampfaktionsauswertung anbinden.
- Ausgabe als nachvollziehbarer Kampfblock in den Szenenkontext integrieren.
- Rollen-/Policy-Durchsetzung konsistent zu bestehenden Mustern (`Spielleitung`/`Co-Spielleitung` nur).

### Akzeptanzkriterien
- Ohne Flag keine sichtbare Änderung am Standardfluss.
- Mit Flag ist Formular nur für berechtigte Rollen sichtbar und nutzbar.
- Spieler sehen kein Kampfaktionsformular.
- Kampfblock wird nach Auswertung stabil und verständlich angezeigt.
- Normaler Post-Editor bleibt unverändert.

### Nicht tun
- Keine Spielerformulare für Kampfaktionen.
- Keine automatische Spieler-Kampfqueue.
- Keine Mehrfachaktions-Phasenlogik.
- Keine Initiative-Engine.

### Verifikation
- Feature-Tests für Sichtbarkeit, Authorization und World-Context.
- Regressionstest für normalen Szenen-Postflow.
- Relevante Analyse/Tests ausführen.

## 7. PR-4 — Kampfphasen

### Aufgaben
- Mehrere Aktionen pro Phase sammeln und gemeinsam auswerten.
- Deterministische Auswertungsreihenfolge festlegen und dokumentieren.
- Kampfphasen-Log einführen.
- Optionales Tabellenzielbild umsetzen:
  - `combat_phases`
  - `combat_actions`
- Kompatibilität zu Einzelaktions-MVP sicherstellen.

### Akzeptanzkriterien
- Eine Phase kann mehrere Aktionen enthalten.
- Auswertung ist reproduzierbar und dokumentiert.
- Phase und Aktionen sind historisch nachvollziehbar.
- Bestehende Einzelaktionsfunktion bleibt kompatibel.

### Nicht tun
- Keine Initiative-Engine.
- Keine Reaktionsfenster.
- Keine taktische Rundenschleife.
- Keine WebSockets.

### Verifikation
- Unit-Tests für Reihenfolge und Aggregation.
- Feature-Tests für Phasenstart, Sammlung, Auswertung und Sichtbarkeit.
- Analyse und passende Testfilter ausführen.

## 8. PR-5 — Magie

### Aufgaben
- Generische Zauberaktion in den Kampfkontext integrieren.
- `MagicService` mit d100-Logik einführen.
- Mindestfelder unterstützen:
  - Zaubername
  - Zauberwert
  - AE-Kosten
  - Ziel
  - Modifikator
  - Effektart
- AE-Kosten beim Wirken anwenden.
- Zielwirkung systematisch verarbeiten:
  - LE-Schaden
  - LE-Heilung
  - AE-Verlust
  - Attribut-Modifikator
  - rein erzählerischer Effekt

### Akzeptanzkriterien
- Zauberaktionen sind nachvollziehbar und reproduzierbar auswertbar.
- AE-Kosten werden beim Wirken angewendet.
- Effektarten werden sauber unterschieden und dokumentiert.
- Nichtmagische Kampfpfade bleiben stabil.

### Nicht tun
- Kein vollständiger Zauberkatalog.
- Keine Spell-Catalog-Engine.
- Keine komplexe Regelbibliothek je Zauberfamilie.

### Verifikation
- Unit-Tests für AE-Kosten und Effektarten.
- Feature-Tests für autorisierte Nutzung und Ergebnisdarstellung.
- Analyse und passende Testfilter ausführen.

## 9. PR-6 — Zustände und temporäre Effekte

### Aufgaben
- Temporäre Effekte auf `*_current`, LE und AE modellieren.
- Ablaufmodell einführen:
  - manuell
  - nach Szene
  - nach Phase
- UI-Anzeige reduzierter Werte im Charakterkontext ergänzen.
- Konfliktregeln für mehrere gleichzeitige Effekte definieren.

### Akzeptanzkriterien
- Temporäre Effekte verändern aktuelle Werte, nicht kanonische Maximalwerte.
- Ablaufmodell ist eindeutig und testbar.
- Auslaufende Effekte stellen Werte korrekt wieder her oder reduzieren Modifikatoren korrekt.
- Anzeige ist für Spielleitung und Spieler nachvollziehbar.

### Nicht tun
- Keine komplexe Buff/Debuff-Engine mit vollständiger Regelmatrix.
- Keine Echtzeit-Tick-Mechanik.
- Keine WebSocket-abhängige Effektverarbeitung.

### Verifikation
- Unit-Tests für Effektanwendung, Clamping und Ablauf.
- Feature-Tests für Sichtbarkeit und Ablauftrigger.
- Analyse und passende Testfilter ausführen.

## 10. Regression-Checkliste
- Bestehender Post-Flow bleibt unverändert.
- Bestehende Einzel-Proben bleiben unverändert.
- Character-Show/Edit bleibt nutzbar.
- Rollenmodell bleibt unverändert.
- World-Context-Guards bleiben intakt.
- `composer analyse` bleibt grün.
- Relevante Feature-/Unit-Tests bleiben grün.
