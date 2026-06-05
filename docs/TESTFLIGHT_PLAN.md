# Testflight-Plan

## Zweck

Dieses Dokument beschreibt einen kontrollierten Testlauf mit 3-5 Testnutzern.
Es ist kein Release-Statusdokument. Aktueller Release- und Live-Status steht
in `docs/STATUS.md`.

## Ziel

Pruefen, ob C76-RPG fuer echte Play-by-Post-Nutzung verstaendlich, stabil und
angenehm genug ist.

Der Testflight soll nicht alle Features pruefen, sondern die Kernfrage
beantworten:

```text
Koennen neue Spieler eine Kampagne betreten, einen Charakter nutzen,
Beitraege lesen und schreiben, ohne staendig externe Erklaerung zu brauchen?
```

## Teilnehmer

- 1 Spielleitung
- 3-5 Spieler
- optional 1 technischer Beobachter oder Zweitkonto

## Voraussetzungen

Vor Start:

- produktiver Stand extern verifiziert
- Smoke-Check durchgefuehrt
- Testkampagne angelegt
- Testszene vorbereitet
- mindestens ein Beispiel-Handout
- mindestens ein Story-Log-Eintrag
- mindestens ein Charakter- oder Charaktererstellungsweg
- Feedbackkanal bereit
- Notfallkontakt ausserhalb der Plattform bekannt
- Backup vor Teststart vorhanden

Falls reproduzierbare Testdaten in einer nicht-produktiven Umgebung benoetigt
werden, kann der vorhandene Runbook-Seed genutzt werden:

```bash
php artisan dev:testflight:seed --world=<world-slug> --password='<starkes-passwort>'
```

Der Seed ist in Produktion blockiert. Details stehen in
`OPERATIONS_RUNBOOK.md`.

## Rollen

### Spielleitung

- Kampagne vorbereiten
- Spieler einladen
- Szene eroeffnen
- Feedback sammeln
- technische Probleme dokumentieren

### Spieler

- Einladung annehmen
- Profil pruefen
- Charakter anlegen oder uebernehmen
- Szene lesen
- IC-Beitrag schreiben
- OOC-Hinweis testen
- Feedback geben

## Testskript

### Schritt 1: Einstieg

Spieler pruefen:

- Login funktioniert
- Dashboard verstaendlich
- Einladung auffindbar
- Kampagne auffindbar
- naechste Aktion erkennbar

### Schritt 2: Charakter

Spieler pruefen:

- Charakter anlegen oder auswaehlen
- Werte verstehen
- Avatar/Bild optional
- Charakter in Kampagne nutzbar

### Schritt 3: Szene lesen

Spieler pruefen:

- Szene oeffnen
- Einleitung verstehen
- Bilder/Handouts finden
- letzte Beitraege finden
- ungelesene Inhalte erkennen

### Schritt 4: Schreiben

Spieler pruefen:

- IC-Beitrag schreiben
- OOC-Hinweis schreiben
- Formatierung verstehen
- Vorschau/Editor nicht verwirrend
- Beitrag nach dem Speichern finden

### Schritt 5: Spielleitung

SL prueft:

- Beitrag moderieren, falls noetig
- Handout sichtbar machen
- Story-Log aktualisieren
- Spieler kontaktieren
- Szene fortsetzen

### Schritt 6: Feedback

Alle beantworten kurz:

- Was war unklar?
- Wo hast du gesucht?
- Was hat gut funktioniert?
- Was hat gestoert?
- Was hat gefehlt?
- Wuerdest du damit weiterspielen?

## Beobachtungskriterien

Erfolgreich, wenn:

- Spieler finden ihren Einstieg ohne externe Schritt-fuer-Schritt-Anleitung
- mindestens ein IC-Beitrag pro Spieler entsteht
- OOC/IC nicht verwechselt wird
- SL kann sinnvoll reagieren
- keine kritischen Fehler auftreten
- Feedback zeigt konkrete Verbesserungen statt Grundsatzverwirrung

Problematisch, wenn:

- Spieler finden Kampagne oder Szene nicht
- Charakteranlage blockiert
- Post-Editor verwirrt
- mobile Nutzung bricht
- Berechtigungen verhindern sinnvolle Nutzung
- Spielleitung muss staendig ausserhalb der Plattform erklaeren

## Feedbackkanal

Moeglichst einfach:

- internes Feedbackformular
- SL-Kontakt
- dedizierter OOC-Thread
- alternativ externe Liste waehrend Testflight

Jedes Feedback sollte enthalten:

- Nutzerrolle
- Seite/Funktion
- Problem
- erwartetes Verhalten
- tatsaechliches Verhalten
- Screenshot optional

## Exit-Kriterien

Testflight gilt als erfolgreich, wenn:

- Kernfluss funktioniert
- keine kritischen Berechtigungs- oder Datenverluste auftreten
- die wichtigsten UX-Probleme dokumentiert sind
- eine priorisierte Nacharbeitsliste entsteht

Testflight abbrechen, wenn:

- Login oder Kampagnenzugriff grundsaetzlich fehlschlaegt
- Datenverlust auftritt
- vertrauliche Inhalte sichtbar werden
- mehrere Nutzer nicht schreiben koennen
- produktive Instanz instabil wird

## Nachbereitung

Nach dem Testflight:

- Feedback clustern
- Bugs von Produktwuenschen trennen
- Top-5 Verbesserungen definieren
- Roadmap aktualisieren
- keine Grossfeatures aus Einzelmeinungen ableiten
- technische Fehler zuerst beheben

## Deployment- und Smoke-Hinweise

Vor dem Test:

- produktiven Stand extern verifizieren
- `APP_VERSION` / `APP_BUILD` pruefen, falls verfuegbar
- Smoke-Check durchfuehren
- Admin-Zugriff pruefen
- Backup pruefen

Nach dem Test:

- Logs pruefen
- Fehlermeldungen sammeln
- offene Jobs/Queue pruefen
- Medien/Uploads pruefen
- Feedback sichern
