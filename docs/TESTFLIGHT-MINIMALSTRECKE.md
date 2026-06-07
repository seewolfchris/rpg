# Testflight-Minimalstrecke

Stand: 2026-06-07  
Basis: `docs/PRODUKT-KOMMANDOSTAND.md`, `docs/TESTFLIGHT_PLAN.md`, `docs/PRODUCT_ROADMAP.md`

Diese Minimalstrecke ist absichtlich enger als der vollstaendige Testflight-Plan. Sie prueft nur, ob ein neuer Spieler ohne dauernde externe Erklaerung vom Login bis zur ersten verwertbaren Rueckmeldung kommt.

## 1. Ziel des Minimal-Testflights

Ziel ist die Beantwortung genau einer Frage:

```text
Kann ein neuer Spieler nach dem Login das Dashboard verstehen, eine Kampagne und Szene finden, seinen Charakter einordnen, einen ersten IC-Beitrag schreiben, OOC als Meta-Hinweis verstehen und Rueckmeldung geben?
```

Der Test ist erfolgreich genug, wenn der Kernfluss einmal real durchlaufen wird und die groessten Verstaendnisbrueche beobachtet werden. Es wird nicht erwartet, dass alle Nebenbereiche angenehm, vollstaendig oder final wirken.

Kein Live-Status wird aus dem Repository abgeleitet. Falls der Test auf einer produktiven Instanz laeuft, muessen Version, Build, Smoke und Backup vorher extern verifiziert werden.

## 2. Was ausdruecklich NICHT getestet wird

- Registrierung, Freischaltung und Passwort-Reset. Testkonten muessen vorher bereitstehen.
- Kampagnenerstellung durch Spieler.
- Szenenerstellung durch Spieler.
- Admin-Benutzerverwaltung.
- Weltenverwaltung und Enzyklopaedie-Administration.
- Handout-Erstellung, Story-Log-Pflege und private Notizen als Hauptaufgabe.
- Web Push, Benachrichtigungspraeferenzen und Szenen-Abo-Verwaltung.
- Offline-Queue, PWA-Installation und Service-Worker-Verhalten.
- Rangliste, Punkte und Gamification.
- Kampf, Magie, Konfliktakteure, GM-Proben und Inventar-Schnellaktionen.
- Editor-Live-Preview, Draft-Autosave, Reactions, Mentions oder andere Flag-Features.
- Performance, Last, Browser-Matrix oder Mobile-Spezialfaelle.
- Inhaltliche Qualitaet der Lore oder Regeltexte jenseits des OOC/IC-Verstaendnisses.
- Neue Funktionen, neue UI-Entwuerfe oder grosse Umbauideen.

## 3. Rollen im Test

### Spielleitung

- Bereitet eine vorhandene oder neue Testkampagne und eine offene Testszene vor.
- Stellt sicher, dass der Spieler Zugang zur Kampagne hat.
- Haelt sich waehrend des Spielerflusses zurueck und beobachtet nur.
- Greift nur ein, wenn ein Abbruchkriterium eintritt oder der Spieler blockiert.
- Sammelt Beobachtungen und Rueckmeldungen.

### Neuer Spieler

- Nutzt ein vorbereitetes aktives Testkonto.
- Folgt nur dem sichtbaren Produktfluss.
- Denkt laut, wenn etwas unklar ist.
- Schreibt einen kurzen IC-Beitrag.
- Findet oder versteht den OOC-Hinweis.
- Gibt am Ende Rueckmeldung.

### Optional Beobachter/Zweitkonto

- Stoppt Zeiten und notiert Suchbewegungen.
- Gibt keine Hilfestellung, ausser die Spielleitung entscheidet den Abbruch.
- Prueft bei Bedarf mit einem zweiten Konto, ob der Spielerbeitrag sichtbar beziehungsweise auffindbar ist.
- Dokumentiert nur Beobachtungen, keine Loesungsvorschlaege waehrend des Tests.

## 4. Vorbereitung durch die Spielleitung

Vor dem Test muessen diese Punkte erledigt sein:

1. Testkonto ist aktiv und Login-Daten liegen bereit.
2. Eine aktive Welt ist bekannt.
3. Eine Testkampagne ist fuer den Spieler sichtbar oder der Spieler hat eine offene Einladung.
4. Eine offene Testszene existiert.
5. Die Szene enthaelt eine kurze, eindeutige Ausgangslage.
6. Mindestens ein nutzbarer Charakter ist vorhanden oder der Charaktererstellungsweg ist bewusst Teil der Vorbereitung.
7. Wenn ein Charakter schon existiert: Name und Zweck des Charakters sind fuer den Spieler erkennbar.
8. In der Szene ist OOC erlaubt oder ein vorhandener OOC-Hinweis ist sichtbar.
9. Feedbackweg ist festgelegt: vorhandener OOC-Post, vorhandener SL-Kontakt oder externe Notiz waehrend des Tests.
10. Testleitung hat eine Stoppuhr oder einfache Zeitnotiz bereit.
11. Abbruchkriterien sind allen Beteiligten bekannt.
12. Es wird nicht erklaert, wo geklickt werden muss. Vorab erlaubt ist nur der Testsatz: "Bitte versuche, dich einzuloggen, die Kampagne und Szene zu finden, deinen Charakter zu verstehen und einen ersten IC-Beitrag zu schreiben."

Nicht vorbereiten:

- Keine neuen UI-Hilfen bauen.
- Keine neuen Features aktivieren.
- Keine Sondertexte in Code oder Views einfuegen.
- Keine Testdaten nutzen, die nur mit persoenlicher Erklaerung funktionieren.

## 5. Testablauf Spieler Schritt fuer Schritt

Maximale Dauer: 30 bis 45 Minuten pro Spieler.

1. Login
   - Spieler oeffnet die Plattform.
   - Spieler meldet sich mit vorbereitetem Testkonto an.
   - Beobachten: Findet er den Login? Gibt es Verwirrung durch Accountstatus, Welt oder Startseite?

2. Dashboard verstehen
   - Spieler bleibt nach dem Login auf dem ersten relevanten eingeloggten Bildschirm.
   - Spieler sagt laut, was er als naechste Aktion versteht.
   - Beobachten: Wird "naechste Aktion" erkannt oder konkurrieren Punkte, Tutorial, Navigation, Weltwechsel und Nebenbereiche?

3. Kampagne finden
   - Spieler versucht, die Testkampagne zu finden.
   - Wenn eine Einladung vorhanden ist, soll er sie aus dem Produktfluss heraus finden und annehmen.
   - Wenn die Kampagne bereits sichtbar ist, soll er sie oeffnen.
   - Beobachten: Sucht er unter Dashboard, Kampagnen, Einladungen, Welten oder Wissen?

4. Szene finden
   - Spieler oeffnet innerhalb der Kampagne die vorgesehene Testszene.
   - Beobachten: Versteht er, welche Szene aktuell ist? Sind Filter, Status, "Neu", "Gelesen" oder Thread-Labels verwirrend?

5. Charakter verstehen oder auswaehlen
   - Spieler findet heraus, mit welchem Charakter er schreiben soll.
   - Wenn ein Charakter vorhanden ist, prueft er kurz Name, Rolle/Konzept und ob der Charakter zur Szene passt.
   - Wenn mehrere Charaktere sichtbar sind, waehlt er einen.
   - Beobachten: Ist der Charakterbezug vor dem Schreiben klar oder erst im Postformular erkennbar?

6. Szene lesen
   - Spieler liest die Ausgangslage und mindestens den letzten relevanten IC-Beitrag.
   - Beobachten: Findet er den eigentlichen Lesefluss? Wird er durch Handouts, Chronik, Notizen, Bookmarks, Romanmodus oder Toolbar abgelenkt?

7. Ersten IC-Beitrag schreiben
   - Spieler oeffnet das Schreibfeld.
   - Spieler waehlt IC, seinen Charakter und schreibt einen kurzen Beitrag.
   - Inhaltliche Vorgabe: 3 bis 6 Saetze aus Sicht der Figur, eine klare Handlung oder Reaktion.
   - Beobachten: Versteht er IC, Charakterauswahl, Format und Speichern? Findet er den Beitrag nach dem Absenden wieder?

8. OOC-Hinweis verstehen
   - Spieler sucht oder erkennt, wo ein kurzer OOC-Hinweis stehen wuerde.
   - Spieler muss keinen langen OOC-Dialog fuehren. Ein Satz reicht, zum Beispiel: "OOC: Ich bin unsicher, ob mein Charakter die Tuer schon sehen kann."
   - Beobachten: Verwechselt er IC und OOC? Ist klar, dass OOC Meta-Abstimmung und nicht Figurenhandlung ist?

9. Rueckmeldung geben
   - Spieler beantwortet unmittelbar nach dem Test die Rueckmeldefragen aus Abschnitt 10.
   - Rueckmeldung kann ueber den vorher festgelegten vorhandenen Kanal oder extern notiert werden.
   - Beobachten: Sind die Probleme konkret genug, um danach priorisiert zu werden?

## 6. Testablauf Spielleitung Schritt fuer Schritt

Vor dem Spielerstart:

1. Testkonto pruefen.
2. Kampagnenzugang pruefen.
3. Testszene oeffnen und sicherstellen, dass sie offen und sinnvoll lesbar ist.
4. Charakterzugang pruefen.
5. Feedbackweg festlegen.
6. Dem Spieler nur die Minimalaufgabe geben, keine Klickanleitung.

Waehrend des Spielerflusses:

1. Nicht vormachen.
2. Nicht die Navigation erklaeren.
3. Nicht Begriffe erklaeren, solange der Spieler weiterkommt.
4. Zeit bis zum Kampagnenfund notieren.
5. Zeit bis zum Szenenfund notieren.
6. Zeit bis zum Schreibfeld notieren.
7. Alle Suchwege und Sackgassen notieren.
8. Nur eingreifen, wenn ein Abbruchkriterium erreicht ist.

Nach dem Spielerbeitrag:

1. Pruefen, ob der IC-Beitrag gespeichert und auffindbar ist.
2. Pruefen, ob der Spieler OOC als Meta-Hinweis verstanden hat.
3. Rueckmeldung einsammeln.
4. Technische Fehler, Berechtigungsprobleme und reine Verstaendnisprobleme getrennt notieren.
5. Keine Loesungen im Testtermin entwerfen. Erst nach allen Testpersonen clustern.

## 7. Beobachtungsbogen

Pro Spieler eine Zeile oder ein Blatt ausfuellen.

```text
Spieler:
Datum:
Geraet/Browser, falls bekannt:
Testkonto:
Testwelt:
Testkampagne:
Testszene:

Login erfolgreich? Ja/Nein
Zeit bis Dashboard verstanden:
Was hielt der Spieler fuer die naechste Aktion?

Kampagne gefunden? Ja/Nein
Zeit bis Kampagne:
Wo wurde zuerst gesucht?
Welche Begriffe waren unklar?

Szene gefunden? Ja/Nein
Zeit bis Szene:
Welche UI-Elemente haben geholfen?
Welche UI-Elemente haben abgelenkt?

Charakter verstanden oder ausgewaehlt? Ja/Nein
War klar, welcher Charakter genutzt werden soll?
Musste die Spielleitung helfen?

IC-Beitrag geschrieben? Ja/Nein
Zeit bis Beitrag gespeichert:
Wurde der Beitrag nach dem Speichern wiedergefunden?
Gab es Verwirrung bei IC, Charakter, Format oder Speichern?

OOC-Hinweis verstanden? Ja/Nein
Was glaubte der Spieler, wofuer OOC gedacht ist?
Wurde IC/OOC verwechselt?

Rueckmeldung gegeben? Ja/Nein
Top-1 Verwirrung:
Top-1 Stoerung:
Top-1 positives Signal:
Zitat oder konkrete Beobachtung:

Abbruch? Ja/Nein
Wenn ja, Grund:
```

## 8. Abbruchkriterien

Test fuer diese Person abbrechen, wenn:

- Login nicht funktioniert.
- Accountstatus blockiert und kein vorbereitetes aktives Konto verfuegbar ist.
- Kampagnenzugriff grundsaetzlich fehlschlaegt.
- Szene nicht oeffnet oder Zugriff verweigert wird.
- Kein nutzbarer Charakter verfuegbar ist und Charaktererstellung nicht Teil dieser Testsitzung sein soll.
- Beitrag kann nicht gespeichert werden.
- Der Spieler ist laenger als 10 Minuten komplett blockiert und findet keine naechste Handlung.
- Berechtigungen verhindern den Kernfluss.
- Vertrauliche Inhalte werden unerwartet sichtbar.
- Technischer Fehler verhindert Weiterarbeit.

Nicht abbrechen bei:

- Unklarer Begrifflichkeit, solange der Spieler weiterprobiert.
- Umwegen in Navigation oder Dashboard.
- Unsicherheit im Text des IC-Beitrags.
- OOC/IC-Verwechslung, wenn sie beobachtbar und danach korrigierbar ist.

## 9. Erfolgskriterien

Minimal erfolgreich, wenn:

- Spieler loggt sich ein.
- Spieler kann sagen, was das Dashboard ihm als naechste Aktion nahelegt.
- Spieler findet Kampagne ohne Klickanleitung.
- Spieler findet Szene ohne Klickanleitung.
- Spieler versteht oder waehlt einen Charakter.
- Spieler schreibt mindestens einen IC-Beitrag.
- Spieler kann OOC grob als Meta-Hinweis ausserhalb der Figurenhandlung beschreiben.
- Spieler gibt konkrete Rueckmeldung.
- Keine kritischen Berechtigungs-, Datenverlust- oder Sichtbarkeitsprobleme treten auf.

Stark erfolgreich, wenn zusaetzlich:

- Kampagne wird innerhalb von 5 Minuten gefunden.
- Szene wird innerhalb von 5 weiteren Minuten gefunden.
- Schreibfeld wird ohne Hilfe gefunden.
- IC/OOC wird nicht verwechselt.
- Die Rueckmeldung benennt konkrete Verbesserungen statt Grundsatzverwirrung.

Nicht erfolgreich, wenn:

- Mehrere Spieler dieselbe Kernstelle nicht ueberwinden.
- Der erste IC-Beitrag nur mit Schritt-fuer-Schritt-Hilfe entsteht.
- Spieler nicht versteht, wo Kampagne, Szene oder Charakter zusammenhaengen.
- Feedback zeigt, dass das Produktziel unklar bleibt.

## 10. Welche Rueckmeldungen gesammelt werden

Direkt nach dem Test diese Fragen stellen:

1. Was wolltest du als erstes tun, nachdem du das Dashboard gesehen hast?
2. Wo hast du die Kampagne gesucht?
3. Wo hast du die Szene gesucht?
4. War klar, welcher Charakter zu benutzen ist?
5. Was hat dich beim Schreiben des IC-Beitrags irritiert?
6. Was glaubst du, wofuer OOC gedacht ist?
7. Welche Stelle hat dich am meisten aufgehalten?
8. Welche Stelle war klarer als erwartet?
9. Was muesste anders sein, damit du beim naechsten Mal ohne Hilfe weiterspielen koenntest?
10. Wuerdest du mit diesem Ablauf eine zweite Szene spielen?

Rueckmeldungen werden nach Typ getrennt:

- Blocker: verhindert Kernfluss.
- UX-Verwirrung: Spieler kommt weiter, aber nur mit Suchbewegung oder falscher Annahme.
- Inhaltliche Unklarheit: Kampagnen-/Szenentext oder Charakterkonzept nicht klar.
- Technischer Fehler: Fehlerseite, Speichern scheitert, Berechtigung falsch, Beitrag verschwindet.
- Wunsch: Verbesserungsidee, die nicht fuer den ersten Kernfluss zwingend ist.

## 11. Top-Entscheidung nach dem Test

Nach dem Minimal-Testflight wird genau diese Entscheidung getroffen:

```text
Ist der vorhandene Kernfluss gut genug, um mit derselben Minimalstrecke weitere 3 bis 5 Nutzer zu testen?
```

Moegliche Antworten:

- Ja: Keine neuen Features bauen. Naechsten Test mit gleicher Strecke durchfuehren und nur offensichtliche Text-/Vorbereitungsfehler korrigieren.
- Ja, aber mit Fixes: Nur die kleinsten Blocker beheben, die Login, Dashboard, Kampagne, Szene, Charakter, IC, OOC oder Feedback direkt betreffen.
- Nein: Testflight stoppen, Top-Blocker beheben, danach dieselbe Minimalstrecke erneut testen.

Nicht entscheiden:

- Ob Kampf/Magie ausgebaut wird.
- Ob eine neue Navigation gebaut wird.
- Ob ein grosser Editor-Umbau noetig ist.
- Ob neue Community-Features gebaut werden.
- Ob Roadmap-Phasen vorgezogen werden.

## 12. Liste: Was nach dem Test zuerst behoben wird

Nur beheben, wenn es im Test beobachtet wurde:

1. Login- oder Accountstatus-Blocker.
2. Fehlender oder falscher Kampagnenzugriff.
3. Kampagne auf Dashboard/Navigation nicht auffindbar.
4. Szene in Kampagne nicht auffindbar.
5. Charakter vor dem Schreiben unklar oder nicht waehlbar.
6. Schreibfeld nicht auffindbar.
7. IC-Beitrag kann nicht gespeichert werden.
8. Beitrag ist nach dem Speichern nicht auffindbar.
9. OOC/IC wird durch UI oder Text systematisch verwechselt.
10. Feedbackweg ist fuer Spieler nicht erreichbar oder unklar.

Alle Punkte muessen direkt auf die Minimalstrecke einzahlen. Alles andere wird geparkt.

## 13. Liste: Was bewusst spaeter kommt

- Vollstaendiges Spieler-Onboarding ueber alle Produktbereiche.
- Groesseres SL-Cockpit.
- Handout-Kategorien und tiefere Handout-Integration.
- Story-Log-Automatisierung oder Uebernahme aus Posts.
- Erweiterte Szenenstatus wie "wartet auf SL" oder "wartet auf Spieler".
- Verbesserter Post-Editor, Live-Preview, Draft-Autosave und Komfortfunktionen.
- Benachrichtigungspraeferenzen und Abo-Feinschliff.
- Welt-Startseiten, Enzyklopaedie-Kurierung und Content-Templates.
- Kampf-, Magie- und Konfliktwerkzeuge.
- Admin-Gesundheitsseite, Export-/Backup-Komfort und betriebliche Erweiterungen.
- Mobile-App, Realtime-Chat, WebSocket-Kern, Battlemap, Plugin-System oder Marktplatz.

## Executive Summary

- Ist der Testflight jetzt sinnvoll? Ja, als enger Minimaltest. Die vorhandenen Dokumente priorisieren genau diesen Kernfluss vor weiterem Ausbau.
- Was ist das groesste Risiko? Neue Spieler verlieren sich vor dem ersten IC-Beitrag in Dashboard, Kampagnen-/Szenennavigation, Charakterbezug oder IC/OOC-Begriffen.
- Was ist der naechste konkrete Schritt? Eine Testkampagne, eine offene Szene, ein aktives Testkonto und einen klaren Feedbackweg vorbereiten und die Minimalstrecke mit einem neuen Spieler beobachten.
