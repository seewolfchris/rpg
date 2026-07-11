# C76-RPG – Arbeitsanweisungen für Codex

## Projekt

- Laravel 12 mit PHP 8.5
- Blade, HTMX, Alpine.js und Tailwind CSS
- MariaDB/MySQL, Redis für Cache, Queue und Sessions
- PHPUnit, PHPStan Level 8 und Playwright
- Keine externen CDNs verwenden
- Das Projekt wird lokal in VS Code bearbeitet

## Arbeitsweise

- Vor Änderungen zuerst die bestehende Implementierung, Architektur und Tests untersuchen.
- Bei größeren oder riskanten Aufgaben zuerst einen konkreten Umsetzungsplan erstellen.
- Änderungen auf den ausdrücklich angeforderten Umfang beschränken.
- Keine benachbarten Funktionen ohne fachlichen Grund umgestalten.
- Bestehende Projektkonventionen bevorzugen.
- Bestehende uncommittete Änderungen des Benutzers niemals überschreiben, zurücksetzen oder ungefragt umformatieren.
- Keine neuen Composer-, NPM- oder sonstigen Abhängigkeiten ohne ausdrückliche Zustimmung hinzufügen.
- Keine Commits und keinen Push durchführen, außer der Benutzer fordert dies ausdrücklich an.
- Ohne ausdrücklichen Auftrag prüft der Benutzer Änderungen und führt Git-Commit und Push selbst aus.

## Entscheidungsregeln

- Bei Unklarheiten zuerst vorhandenen Code, Tests und Dokumentation als Quelle verwenden.
- Nur nachfragen, wenn mehrere fachlich unterschiedliche Lösungen möglich sind oder eine Änderung nicht sicher rückgängig gemacht werden kann.
- Öffentliche Routen, APIs, Events und Datenbankschemata nicht unbeabsichtigt brechen.
- Kommentare sollen fachliche Gründe oder nicht offensichtliche Randbedingungen erläutern und nicht lediglich den sichtbaren Code wiederholen.
- Keine spekulativen Abstraktionen für nur hypothetische zukünftige Anforderungen einführen.

## Maßgebliche Projektdokumentation

- Architektur sowie Controller-, Action- und Mutationsgrenzen: `docs/ARCHITECTURE.md`
- Blade-, HTMX- und Alpine-Konventionen: `docs/FRONTEND.md`
- Exakte Release- und Qualitäts-Gates: `docs/RELEASE-CHECKLISTE.md`
- Release-, Entwicklungs-, Live-, Build- und Gate-Status: `docs/STATUS.md`
- Bekannte Fehler und Risiken: `docs/KNOWN_ISSUES.md`
- Technische Schulden: `docs/TECHNICAL_DEBT.md`
- Architekturentscheidungen: `docs/adr/`
- Diese Inhalte nicht vollständig in `AGENTS.md` duplizieren.

## Architektur

- Keine Geschäftslogik in Controllern.
- Keine direkten Datenbanktransaktionen oder umfangreiche Persistenzlogik in Controllern.
- Mutationen Actions-first umsetzen; mutierende Actions sind grundsätzlich `final`.
- Für weitere Controller-, Action- und Mutationsgrenzen ist `docs/ARCHITECTURE.md` maßgeblich.
- Autorisierung grundsätzlich über Policies und kampagnengebundene Rollen prüfen.
- Globale Adminrechte und kampagnengebundene Rollen getrennt behandeln.
- Kein globaler Spielleiterstatus als Voraussetzung für Kampagnenrechte.
- Datenbankänderungen müssen migrationsfähig, rückwärtsverträglich und getestet sein.
- Nebenläufigkeit, Idempotenz und Duplicate-Key-Situationen berücksichtigen.

## Frontend und Build-Artefakte

- Bestehende Blade-, HTMX- und Alpine.js-Muster sowie `docs/FRONTEND.md` befolgen.
- `public/build` und `public/js/character-sheet.global.js` sind generierte, aber versionierte Build-Artefakte.
- Diese Artefakte nicht manuell bearbeiten, nach relevanten Quelländerungen mit `npm run build` regenerieren und anschließend gemäß `docs/RELEASE-CHECKLISTE.md` auf Drift prüfen.

## Sprache und Fachbegriffe

- Benutzeroberfläche und RPG-Fachbegriffe grundsätzlich auf Deutsch formulieren.
- „Spielleiter“ beziehungsweise „SL“ statt „Game Master“ oder „GM“ in sichtbaren Texten verwenden.
- Vorhandene deutsche Begriffe und Benennungen beibehalten.
- Keine unnötigen Anglizismen in sichtbaren Benutzertexten einführen.

## Tests und Qualitätskontrolle

- Für geändertes Verhalten relevante Tests ergänzen oder aktualisieren.
- Zunächst fokussierte Tests für den geänderten Bereich ausführen.
- Bei größeren Änderungen anschließend die vollständigen vorgesehenen Qualitätsprüfungen ausführen.
- PHPStan Level 8 muss fehlerfrei bleiben.
- Bestehende Tests dürfen nicht zur bloßen Fehlerbeseitigung abgeschwächt oder entfernt werden.
- Abschließend `git diff --check` ausführen.
- Geänderte Dateien und ausgeführte Prüfungen vollständig zusammenfassen.
- Nicht ausgeführte Prüfungen ausdrücklich nennen.

## Verbindliche Prüfkommandos

- Fokussierter PHP-Test: `php artisan test --without-tty --do-not-cache-result --filter=<Name>` oder mit dem Pfad einer Testdatei.
- Statische Analyse: `composer analyse`
- JavaScript-Tests: `npm run test:js`
- End-to-End-Tests: `npm run test:e2e`
- Frontend-Build: `npm run build`
- Schneller Pre-Push-Check: `scripts/pre_push_check.sh`
- Umfangreicher Pre-Push-Check: `scripts/pre_push_check.sh --full --with-build`
- MySQL-spezifische Änderungen benötigen zusätzlich die separaten, in `docs/RELEASE-CHECKLISTE.md` dokumentierten MySQL-Testgruppen.
- Für die vollständigen exakten Qualitäts- und Release-Gates ist `docs/RELEASE-CHECKLISTE.md` maßgeblich.
- `scripts/release_flow.sh` nur bei einem ausdrücklichen Release-Auftrag ausführen; das Skript kann Commits, Pushes und Tags erzeugen.

## Datenbank und Tests

- Migrationen vorwärtskompatibel gestalten und bestehende Daten berücksichtigen.
- Fremdschlüssel, eindeutige Indizes und Datenbank-Constraints passend zu den fachlichen Invarianten einsetzen.
- Tests bevorzugt mit Factories und den bestehenden Testhilfen aufbauen statt Datensätze direkt und wiederholt anzulegen.
- MariaDB/MySQL-spezifisches Verhalten bei Nebenläufigkeit, Sperren, JSON, Indizes und Constraints berücksichtigen.
- SQLite-basierte Tests nicht als alleinigen Nachweis für MariaDB/MySQL-spezifisches Verhalten verwenden.

## Dokumentation

- `docs/STATUS.md` ist die einzige kanonische Quelle für Release-, Entwicklungs-, Live-, Build- und Gate-Status.
- README und Roadmaps dürfen Status nur knapp einordnen und müssen auf `docs/STATUS.md` verweisen.
- Statusangaben nur mit entsprechendem Nachweis aktualisieren; produktive Versionen oder Commits nur mit externem Deployment- oder Build-Nachweis als live kennzeichnen.
- Exakte Gate-Befehle bleiben in `docs/RELEASE-CHECKLISTE.md`.
- Bestehende Dokumentationsstruktur und Terminologie beibehalten.

## Sicherheit

- Keine `.env`-Dateien, Passwörter, Tokens, API-Schlüssel oder privaten Schlüssel anzeigen, verändern oder committen.
- Keine produktiven Datenbanken, Server oder Deployments ohne ausdrücklichen Auftrag verändern.
- Keine destruktiven Git-Befehle wie `git reset --hard`, `git clean -fd` oder `git push --force` ausführen.
- Keine Sicherheitsprüfung umgehen, nur damit Tests bestehen.
- Öffentliche Endpunkte, Autorisierung, CSRF-Schutz, Rate Limits und Datenzugriffe besonders sorgfältig prüfen.

## Abschlussbericht

Nach jeder Umsetzung angeben:

1. Was geändert wurde
2. Welche Dateien betroffen sind
3. Welche Tests und Prüfungen ausgeführt wurden
4. Welche Ergebnisse diese Prüfungen hatten
5. Welche Risiken oder offenen Punkte verbleiben
6. Ob der Git-Arbeitsbaum noch unbeabsichtigte Änderungen enthält
