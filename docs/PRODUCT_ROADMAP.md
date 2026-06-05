# Produkt-Roadmap

## Zweck

Diese Roadmap beschreibt Produktprioritaeten aus Sicht von Spielern,
Spielleitung und Testbetrieb. Sie ist keine Release-Statusquelle. Aktueller
Release-, Live- und Gate-Status steht in `docs/STATUS.md`.

## Leitbild

C76-RPG soll kein generisches Foren- oder Chat-System sein, sondern eine
immersive Play-by-Post-RPG-Plattform.

Prioritaeten:

- Spielbarkeit
- Spielerfuehrung
- Spielleitungsentlastung
- Immersion
- Lesbarkeit
- kontrollierter Testbetrieb
- Wartbarkeit

## Phase 1: Testflight-faehig machen

Ziel: 3-5 echte Testnutzer sollen ohne staendige persoenliche Erklaerung
spielen koennen.

Pakete:

1. Spieler-Onboarding / Erste Schritte
2. Charakter-Assistent
3. Dashboard "Naechste Schritte"
4. SL-Cockpit light
5. Feedbackkanal fuer Testspieler
6. sichtbarer Build-/Versionshinweis fuer Admins
7. Live-Smoke nach Deployment dokumentieren

### Spieler-Onboarding

- kurze Seite "Erste Schritte"
- Erklaerung von Welt, Kampagne, Szene, IC/OOC, Charakter
- Hinweise im Dashboard
- Einladungspfad erklaeren
- nicht als Regelwand, sondern als kompakter Einstieg

### Charakter-Assistent

- linearer Wizard
- Grunddaten
- Welt-/Kampagnenkontext
- Startpaket oder Archetyp
- Attribute
- Biografie
- Avatar optional
- finale Zusammenfassung
- Charakter zur Kampagne anmelden

### Dashboard "Naechste Schritte"

Das Dashboard soll zeigen, was jetzt relevant ist:

- keine Kampagne
- offene Einladung
- kein Charakter
- Charakter wartet auf Freigabe
- neue Beitraege
- offene Moderation fuer SL
- offene SL-Kontakte

### SL-Cockpit light

Kampagnenbezogener Ueberblick:

- aktive Szenen
- wartende Beitraege
- offene Moderation
- Handouts
- Story-Log
- private Notizen
- SL-Kontakte
- Schnellaktionen

### Feedbackkanal

Fuer Testspieler:

- Problem melden
- Verwirrung melden
- Vorschlag senden
- Seitenkontext mitschicken

## Phase 2: Spielleitung entlasten

Pakete:

1. Story-Log staerker integrieren
2. Handouts verbessern
3. Szenenstatus erweitern
4. "Wartet auf SL / wartet auf Spieler"
5. SL-only Lore
6. Kampagnenzusammenfassung

### Story-Log

- aus Beitrag ins Story-Log uebernehmen
- chronologische Kampagnenzusammenfassung
- wichtige Wendepunkte markieren
- spaeter Export als Chronik

### Handouts

- Kategorien: Karte, Brief, Gegenstand, Hinweis, NSC, Ort
- verborgen / enthuellt
- an Szene anheften
- in Beitrag verlinken
- Vorschau im Thread
- SL-interne Notiz

### Szenenstatus

Moegliche Zustaende:

- offen
- pausiert
- abgeschlossen
- archiviert
- wartet auf SL
- wartet auf Spieler

## Phase 3: Spielerkomfort

Pakete:

1. besserer Post-Editor
2. Entwurfsschutz
3. "Zum naechsten ungelesenen Beitrag"
4. Lesemodus weiter polieren
5. Benachrichtigungseinstellungen
6. eigene Abos besser steuerbar

Nicht als Rich-Text-Monstereditor umsetzen. Play-by-Post-Lesbarkeit bleibt
wichtiger als Editor-Komplexitaet.

## Phase 4: Welt und Inhalte

Pakete:

1. Welt-Startseiten ausbauen
2. Enzyklopaedie kuratieren
3. SL-Wissen von Spielerwissen trennen
4. Content-Templates
5. Morhaven/Nebelmarken als Beispielqualitaet

Content-Templates:

- Stadt
- Dorf
- Region
- NSC
- Fraktion
- Kult/Religion
- Monster
- Artefakt
- Handout
- Szenenbeschreibung
- Kampagnenpitch

## Phase 5: Konfliktwerkzeuge

Kampf, Magie und Konflikte bleiben story-first und SL-zentriert.

Nicht bauen:

- taktische Battlemap
- Initiative-Engine als Kern
- Spieler-Kampfqueue
- Echtzeitkampf
- D&D-artige Kampfrundenmaschine

Bauen oder polieren:

- SL-only Kampf-/Magieauswertung
- Konfliktakteure
- NSC-Snapshots
- Ergebnisbloecke im Thread
- Konsequenzlog
- soziale Konflikte
- Verfolgungen
- Rituale
- Seefahrt, Sturm, Navigation
- Ermittlungen unter Zeitdruck

## Phase 6: Betrieb und Vertrauen

Pakete:

1. Admin-Gesundheitsseite
2. sichtbarer Build-/Versionsstand fuer Admins
3. Backup-/Export-Konzept
4. Kampagnenexport
5. Produktions-Smoke-Check dokumentieren

## Explizite Nicht-Ziele

Nicht Teil der absehbaren Produkt-Roadmap:

- Realtime-Chat als Kern
- WebSockets als Pflichtarchitektur
- taktische Battlemap
- SPA-Neuausrichtung
- Plugin-System
- Mobile-App
- KI-Bildgenerator im Produktkern
- oeffentliche Registrierung ohne klare Moderation
- Marktplatz-/Community-Plattform
