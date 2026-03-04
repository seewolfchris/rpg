# Chroniken der Asche - Projekt-Uebersicht (Stand 4. Maerz 2026)

> Quicklinks:
> - Technischer Einstieg: `README.md`
> - Release-Checkliste: `docs/RELEASE-CHECKLISTE.md`
> - Deployment: `docs/PLESK_DEPLOYMENT_FUER_ANFAENGER.md`
> - GitHub/Plesk Setup: `docs/GITHUB_PLESK_SETUP.md`

## Release-Stand
- Aktuelle sichtbare Version: `v0.02-beta`
- Branch-Strategie: `main` lokal <-> `origin/main` (gleiches Ziel, nur lokal vs. remote)
- PHP-Basis: `8.5.x` (Plesk + CLI)

## Aktueller Tech-Stack
- Laravel 12/13
- MySQL / MariaDB
- Tailwind CSS + Blade + Alpine.js
- PWA (Service Worker + Offline-Queue)

## Kern-Philosophie
- Immersion first, Mathe second.
- d100/Prozent-System statt d20.
- Regeln stuetzen die Erzaehlung, dominieren sie nicht.

## Feature-Status
| Bereich | Status | Hinweis |
|---|---|---|
| Auth (Login/Register/Reset) | Fertig | inkl. Mail-Reset |
| Charaktere (CRUD + Avatar + Ownership) | Fertig | Policy-geschuetzt, validiert |
| Charakterbogen (DSA-8, Prozentwerte) | Fertig | Persistenz inkl. LE/AE und Notizen |
| Charakter-Erstellung 2 Modi | Fertig | Real-World zwingt Spezies `mensch` |
| Kampagnen/Szenen/Posts | Fertig | IC/OOC getrennt, Moderation, Revisionen |
| GM-only Proben im Post | Fertig | Anlass/Held/Modifikator, Ergebnis im GM-Post |
| Proben-Persistenz auf Zielcharakter | Fertig | LE/AE-Impact wird gespeichert |
| Benachrichtigungen | Fertig | In-App + optional Mail |
| Gamification (Punkte) | Fertig | post-basierte Punkteevents |
| Wissenszentrum | Fertig | HowTo, Regeln, Enzyklopaedie |
| Enzyklopaedie Admin | Fertig | GM/Admin CRUD Kategorien + Eintraege |
| PWA Basis | Teilweise fertig | Offline-Lesen + Queue aktiv, Push spaeter |
| Push Notifications | Geplant | nach Beta-Phase |

## Wichtige technische Entscheidungen
- Alle Tooling- und Deploy-Kommandos auf Server mit Plesk-PHP 8.5 ausfuehren:
  `/opt/plesk/php/8.5/bin/php artisan ...`
- Charakterformular hat einen robusten globalen Bootstrap-Fallback (`public/js/character-sheet.global.js`), damit Alpine auch bei Asset-Drift initialisiert.
- Service Worker Caches wurden auf `v6` angehoben; Build-Assets (`/build/*`) werden `networkFirst` geladen.

## Versionierungs-Regel (verbindlich)
- Laufende Instanz: `APP_VERSION` in `.env` setzen.
- Repo-Vorlage fuer neue Umgebungen: `APP_VERSION` in `.env.example` aktuell halten.
- Optional: `APP_BUILD` fuer Commit/Build-Kennung nutzen.
- Nach Versionsaenderung auf Server:
  `php artisan optimize:clear` und `php artisan config:cache`.

## Offene Prioritaeten
- Regel-/Hilfetexte weiter auf GM-only-Probenpraxis harmonisieren.
- PWA Push-Benachrichtigungen planen (nach Beta-Stabilisierung).
- Optional: weitere Konsolidierung von Legacy-Texten und Doku.
