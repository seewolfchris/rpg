# C76-RPG – Arbeitsanweisungen für Codex

## Projekt

- Laravel 12 mit PHP 8.5
- Blade, HTMX, Alpine.js und Tailwind CSS
- MariaDB/MySQL, Redis für Cache, Queue und Sessions
- PHPUnit/Pest, PHPStan Level 8 und Playwright
- Keine externen CDNs verwenden
- Das Projekt wird lokal in VS Code bearbeitet

## Arbeitsweise

- Vor Änderungen zuerst die bestehende Implementierung, Architektur und Tests untersuchen.
- Bei größeren oder riskanten Aufgaben zuerst einen konkreten Umsetzungsplan erstellen.
- Änderungen auf den ausdrücklich angeforderten Umfang beschränken.
- Keine benachbarten Funktionen ohne fachlichen Grund umgestalten.
- Bestehende Projektkonventionen bevorzugen.
- Keine Commits und keinen Push durchführen, außer der Benutzer fordert dies ausdrücklich an.
- Ohne ausdrücklichen Auftrag prüft der Benutzer Änderungen und führt Git-Commit und Push selbst aus.

## Entscheidungsregeln

- Bei Unklarheiten zuerst vorhandenen Code, Tests und Dokumentation als Quelle verwenden.
- Nur nachfragen, wenn mehrere fachlich unterschiedliche Lösungen möglich sind oder eine Änderung nicht sicher rückgängig gemacht werden kann.
- Keine neuen Abhängigkeiten ohne ausdrückliche Zustimmung hinzufügen.
- Öffentliche Schnittstellen, Routen, Events und Datenbankschemata nicht unbeabsichtigt brechen.
- Bestehende uncommittete Änderungen des Benutzers nicht überschreiben, zurücksetzen oder ungefragt umformatieren.
- Kommentare sollen fachliche Gründe erläutern und nicht lediglich den sichtbaren Code wiederholen.
- Keine spekulativen Abstraktionen für nur hypothetische zukünftige Anforderungen einführen.

## Architektur

- Keine Geschäftslogik in Controllern.
- Keine direkten Datenbanktransaktionen oder umfangreiche Persistenzlogik in Controllern.
- Bestehende Actions, Services, Policies und Resolver verwenden oder sinnvoll erweitern.
- Autorisierung grundsätzlich über Policies und kampagnengebundene Rollen prüfen.
- Globale Adminrechte und kampagnengebundene Rollen getrennt behandeln.
- Kein globaler Spielleiterstatus als Voraussetzung für Kampagnenrechte.
- Datenbankänderungen müssen migrationsfähig, rückwärtsverträglich und getestet sein.
- Nebenläufigkeit, Idempotenz und Duplicate-Key-Situationen berücksichtigen.

## Projektstruktur und Konventionen

- Anwendungsfälle und schreibende Abläufe bevorzugt in `app/Actions` abbilden.
- Fachliche Logik in den bestehenden Bereichen unter `app/Domain` platzieren.
- Wiederverwendbare technische Logik in `app/Services` oder `app/Support` einordnen.
- Eingabevalidierung in Form Requests und Autorisierung in Policies belassen.
- Bestehende Blade-, HTMX- und Alpine.js-Muster des jeweiligen Bereichs fortführen.
- HTMX für serverseitige Interaktionen und partielle Aktualisierungen verwenden; Alpine.js nur für lokalen UI-Zustand und kleine clientseitige Interaktionen einsetzen.
- Generierte Dateien, gebaute Assets und Abhängigkeitsverzeichnisse nicht manuell bearbeiten.

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

- Fokussierte PHP-Tests: `php artisan test --filter=<Name>` oder `php artisan test <Testdatei>`
- Gesamte PHP-Testsuite: `composer test`
- Statische Analyse: `composer analyse`
- JavaScript-Tests: `npm run test:js`
- HTMX-Sicherheitstests: `npm run test:htmx-safety`
- Service-Worker-Tests: `npm run test:sw`
- Frontend-Build: `npm run build`
- End-to-End-Tests: `npm run test:e2e`
- PHP-Formatierung prüfen: `vendor/bin/pint --test`
- PHP-Formatierung nur bei ausdrücklich gewünschter oder für die Änderung erforderlicher Formatierung ausführen: `vendor/bin/pint`
- Abschließende Whitespace-Prüfung: `git diff --check`
- Umfangreiche oder langsame Prüfungen nur dann auslassen, wenn sie für die Änderung nicht relevant sind; dies im Abschlussbericht begründen.

## Datenbank und Tests

- Migrationen vorwärtskompatibel gestalten und bestehende Daten berücksichtigen.
- Fremdschlüssel, eindeutige Indizes und Datenbank-Constraints passend zu den fachlichen Invarianten einsetzen.
- Tests bevorzugt mit Factories und den bestehenden Testhilfen aufbauen statt Datensätze direkt und wiederholt anzulegen.
- MariaDB/MySQL-spezifisches Verhalten bei Nebenläufigkeit, Sperren, JSON, Indizes und Constraints berücksichtigen.
- SQLite-basierte Tests nicht als alleinigen Nachweis für MariaDB/MySQL-spezifisches Verhalten verwenden.

## Dokumentation

- Dokumentation nur aktualisieren, wenn sich Einrichtung, Bedienung, Architektur, öffentliche Schnittstellen oder fachlich relevante Abläufe ändern.
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
