# ADR 2026-05-05: Phase-basiertes Kampf- und Magiesystem als Spielleitungs-Werkzeug

## 1. Status
Accepted

Amended: 2026-06-04

Implementation status: Partially implemented behind `COMBAT_TOOLS_ENABLED`

## 2. Kontext
C76-RPG ist ein story-first Play-by-Post-System. Der Szenenthread ist das Zentrum des Spiels, und Spieler formulieren dort ihre IC-Absichten in normaler Beitragssprache.

Asynchrones Spiel wird langsam, wenn jede kleine Kampfschritt-Entscheidung als starre Wechselrunde zwischen Spieler und Spielleitung erzwungen wird. Ein klassisches Muster „Spielerzug -> Spielleitungsreaktion -> Spielerzug -> Spielleitungsreaktion“ ist für längere Konflikte im Play-by-Post-Betrieb zu träge.

Gleichzeitig muss Kampf auswertbar und nachvollziehbar bleiben. Es braucht weiterhin Werte, Würfelentscheidungen, Konsequenzen und dokumentierte Ergebnisse im Szenenfluss.

Die bestehende d100-Grundlogik bleibt unverändert und ist weiterhin die rechnerische Basis:

`Wurf + Modifikator <= Zielwert`

## 3. Entscheidung
Kampf wird als phase-basiertes Werkzeug für Spielleitung und Co-Spielleitung modelliert, nicht als taktische Rundenmaschine für Spieler.

Spieler schreiben ihre Absichten weiterhin im normalen IC-Szenenthread. Sie nutzen dafür kein separates Kampf-Formular. Die Spielleitung liest diese Absichten und wertet sie gesammelt aus.

Das Zielbild bleibt phasenbasiert. Für die erste technische Umsetzung wird bewusst kleiner gestartet: Zuerst ist nur eine einzelne Kampfaktion systematisch auswertbar. Mehrere Aktionen pro Phase folgen später.

Ausdrücklich nicht Teil dieser Entscheidung sind:
- Initiative-Engine
- Reaktionsfenster
- taktische Rundenschleife
- Echtzeitabhängigkeiten
- WebSockets

## 4. Attributmodell
Die bestehenden Attributspalten `mu`, `kl`, `in`, `ch`, `ff`, `ge`, `ko`, `kk` bleiben die kanonischen Grund-/Maximalwerte.

Es werden keine zusätzlichen Attribut-Maximalspalten wie `mu_max`, `kl_max`, `in_max`, `ch_max`, `ff_max`, `ge_max`, `ko_max`, `kk_max` eingeführt.

Für temporäre Zustände werden später nur aktuelle Attributwerte ergänzt:
- `mu_current`
- `kl_current`
- `in_current`
- `ch_current`
- `ff_current`
- `ge_current`
- `ko_current`
- `kk_current`

Anzeigeziel im Charakterbogen ist `Max / Aktuell`, zum Beispiel `Mut 60 % / 45 %`.

Regelgrenze:
- `*_current` darf nie über dem berechneten effektiven Maximalwert liegen.
- Effektiver Maximalwert bedeutet: kanonischer Attributwert plus gültige systemische Modifikationen.
- Clamping-Regeln werden analog zur bestehenden Pool-Logik umgesetzt.

`le_max/le_current` und `ae_max/ae_current` bleiben das Referenzmuster für den Umgang mit Maximal- und aktuellen Ressourcenwerten.

Temporäre Zustände wie Panik, Scham, Krankheit, Erschöpfung oder situative Beschädigung wirken auf `*_current`, nicht auf den kanonischen Maximalwert.

## 5. Kampf-V1
Kampf-V1 ist ein SL-only Werkzeug für Spielleitung und Co-Spielleitung.

V1 enthält ausdrücklich:
- kein Spieler-Queue-System
- kein Spielerformular für Kampfaktionen

Spielerabsichten bleiben normale IC-Posts im Szenenthread.

Die Spielleitung erfasst die Auswertung im System, insbesondere:
- Angreifer
- Ziel
- Angriffswert
- Verteidigung
- Schaden
- Rüstungsschutz (RS)
- Modifikator

Das System erzeugt daraus einen nachvollziehbaren Kampfblock und aktualisiert betroffene Werte.

Der normale Posting-Workflow bleibt unverändert.
Bestehende Einzel-Proben bleiben unverändert.

## 6. NPC/Gegner
Kampf darf nicht auf Character-vs-Character beschränkt sein.

V1 unterstützt einfache Gegner/NPCs ohne vollständiges NPC-Modell über Snapshot-/Freifelder mit folgendem Zielbild:
- `actor_type`: `character|npc`
- `actor_character_id`: nullable
- `actor_name`: nullable
- `actor_snapshot`: JSON
- `target_type`: `character|npc`
- `target_character_id`: nullable
- `target_name`: nullable
- `target_snapshot`: JSON

Ein vollständiges NPC-Domänenmodell ist ausdrücklich kein V1-Ziel.

## 7. Magie-Zielbild
Magie gehört zur Zielarchitektur. Sie war nicht Teil des ersten Kampf-MVP; generische Magieauswertung ist inzwischen teilweise hinter `COMBAT_TOOLS_ENABLED` umgesetzt.

Spätere generische Magieauswertung umfasst:
- Zaubername
- Zauberwert
- AE-Kosten
- Ziel
- Modifikator
- Effektart: LE-Schaden, LE-Heilung, AE-Verlust, Attribut-Modifikator oder rein erzählerisch

AE-Kosten werden beim Wirken bezahlt.

Ein vollständiger Zauberkatalog ist nicht Teil von V1.

## 8. Feature-Flag
Kampf- und Magiewerkzeuge werden hinter einem Feature-Flag eingeführt:

`features.combat_tools_enabled`

Standardverhalten bleibt ohne Aktivierung unverändert.

Das Flag trennt sauber:
- lokale Entwicklung
- Staging
- Produktion

## 9. Konsequenzen
Vorteile:
- Kampf bleibt asynchron schnell und story-first.
- Spielleitung und Co-Spielleitung behalten Tempo- und Tonkontrolle.
- Kein Echtzeitdruck für Spieler.
- Charakterwerte werden operativ und nachvollziehbar nutzbar.

Nachteile:
- Höhere Auswertungsverantwortung bei Spielleitung und Co-Spielleitung.
- Automatisierte Spieler-Kampfzüge sind nicht Teil von V1.
- NPC-Snapshot-Lösung ist pragmatisch, aber keine vollständige Gegnerverwaltung.

## 10. Non-Goals
- Kein Echtzeitkampf.
- Keine WebSockets.
- Keine Initiative-Engine.
- Keine taktische Karte.
- Keine vollständige NPC-Verwaltung in V1.
- Kein Zauberkatalog in V1.
- Keine Änderung am normalen IC/OOC-Posting.
- Keine automatische Spieler-Kampfqueue in V1.
- Keine Würfelorgien.
- Keine D&D-artige Kampfrundenmaschine.

## 11. Implementation status
Umgesetzt:
- Einzelne Kampfaktionen als SL-only Werkzeug hinter Feature-Flag.
- Kampfphasen mit Aktionssammlung und Aufloesung.
- Generische Magieaktionen mit AE-Kosten und Effektarten.
- Konfliktakteure fuer Character-/NPC-Snapshots.
- Controller, Services, Routen und Feature-Tests fuer Kampf, Magie und Konfliktakteure.

Weiterhin bewusst begrenzt:
- Kein Spielerformular fuer Kampfaktionen.
- Keine Spieler-Kampfqueue.
- Keine Initiative-Engine, taktische Rundenschleife, WebSockets oder Echtzeitpflicht.

Historische PR-Roadmap:
- PR-0: ADR + TASKS, Dokumentation only.
- PR-1: Attribut-current-Fundament + Charakterbogenanzeige Max/Aktuell.
- PR-2: CombatService MVP für einzelne Kampfaktion + Tests.
- PR-3: SL-only Kampfaktions-UI in Szenenansicht hinter Feature-Flag.
- PR-4: mehrere Aktionen gesammelt als Kampfphase.
- PR-5: generische Magieauswertung.
- PR-6: Zustände/temporäre Effekte mit Ablaufmodell.

## 12. Verifikation
Für PR-0 gilt:
- Keine harte Testpflicht, da nur Dokumentation.
- Optionaler Markdown-/Statuscheck ist ausreichend.

Ab PR-1 gilt:
- fokussierte Unit-/Feature-Tests je PR-Ziel
- später `composer analyse` plus passende Testfilter
- bestehende Posting-, Proben-, Rollen- und Sichtbarkeitsregeln dürfen nicht brechen
