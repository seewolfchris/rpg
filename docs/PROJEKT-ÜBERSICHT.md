# Chroniken der Asche – Master-Handbuch (Stand März 2026)

> Quicklinks:
> - Technischer Einstieg: `README.md`
> - Deployment: `docs/PLESK_DEPLOYMENT_FUER_ANFAENGER.md`
> - GitHub/Plesk Setup: `docs/GITHUB_PLESK_SETUP.md`

## Aktueller Tech-Stack (wichtig!)
- Laravel 12/13
- MySQL / MariaDB (NICHT mehr SQLite!)
- Tailwind CSS + Blade
- PHP 8.5+
- PWA mit Offline-Queue

## Kern-Philosophie (das darf NIE verloren gehen)
- Primaer: Geschichtenerzaehlen & Immersion
- Sekundaer: leichte d20-Mechanik (kein Crunch, keine Mathe-Orgien)
- Spieler soll sich wie in einem Roman fuehlen, nicht wie beim D&D-Charakterbogen ausfuellen

## Feature-Status (immer aktuell halten!)
| Bereich | Status | Hinweis |
|---|---|---|
| Auth (Login/Register/Reset) | Fertig | inkl. Mail-Reset |
| Charaktere (CRUD + Avatar + Ownership) | Fertig | validiert, auth-gebunden |
| Kampagnen/Szenen/Posts | Fertig | IC/OOC, Moderation, Revisionen |
| Dice Roller (d20) | Fertig | inkl. Log in DB |
| Benachrichtigungen | Fertig | In-App + optional Mail |
| Gamification (Punkte) | Fertig | post-basierte Punkteevents |
| Wissenszentrum | Fertig | Uebersicht/HowTo/Regelwerk/Enzyklopaedie |
| Enzyklopaedie Admin | Fertig | GM/Admin CRUD Kategorien + Eintraege |
| PWA Basis | In Arbeit | Offline-Lesen + Queue vorhanden, Push spaeter |
| Push Notifications | Geplant | nach Beta-Phase |
| Charakter-Erstellung 2 Modi | Geplant | Real-World vs. Native Vhal'Tor |

## Wichtige Entscheidungen
- Charakter-Erstellung: Zwei Modi ("Real-World Anfaenger" vs. "Native aus Vhal'Tor")
- Regelwerk: getrennt von Welt & Enzyklopaedie
- IC-Posts sollen in Ich-Perspektive formuliert sein
- System bleibt story-first, Regeln nur unterstuetzend
