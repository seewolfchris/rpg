# Chroniken der Asche - Projekt-Uebersicht (Stand 5. Maerz 2026)

> Quicklinks:
> - Technischer Einstieg: `README.md`
> - Release-Checkliste: `docs/RELEASE-CHECKLISTE.md`
> - Deployment: `docs/PLESK_DEPLOYMENT_FUER_ANFAENGER.md`
> - GitHub/Plesk Setup: `docs/GITHUB_PLESK_SETUP.md`

## Release-Stand
- Aktuelle sichtbare Version: `v0.15-beta`
- Branch-Strategie: `main` lokal <-> `origin/main` (gleiches Ziel, nur lokal vs. remote)
- PHP-Basis: `8.5.x` (Plesk + CLI)

## Letzte Aenderungen
- `in Arbeit` (6. März 2026): Lore-Pack Schritt 5 „Waffen, Rüstungen & Relikte“ mit 10 Enzyklopädie-Einträgen.
- `in Arbeit` (6. März 2026): Lore-Pack Schritt 4 „Regionen & Städte“ mit 5 neuen Enzyklopädie-Einträgen.
- `in Arbeit` (6. März 2026): Lore-Pack Schritt 3 „Monster & Bestiarium“ mit 12 Einträgen inkl. LE/RS/Angriff-Richtwerten.
- `01c6877` (6. März 2026): Sichtbare Texte auf deutsche Umlaute (ä/ö/ü) umgestellt.
- `in Arbeit` (6. März 2026): Lore-Pack Schritt 2 „Magie & Liturgie“ mit neuer Kategorie und 5 Enzyklopädie-Einträgen.
- `in Arbeit` (5. Maerz 2026): Enzyklopaedie-Upgrade mit Kartenlayout, Detailseiten pro Eintrag, sicherem Markdown-Rendering, Spielrelevanz-JSON und Lore-Pack "Voelker & Spezies" (8 Eintraege).
- `d6d9a59` (5. Maerz 2026): Ruestungssektion mit RS und `ausgeruestet` im Charakterbogen, feste Waffenschadenswerte, LE-Schadensminderung durch RS bei GM-Proben, AE nur bei Magiebegabung.

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
| Charakterbogen Inventar/Waffen/Ruestung | Fertig | Inventar mit Stacks, Waffen mit festen Schadenspunkten, Ruestung mit RS + ausgeruestet |
| Charakter-Erstellung 2 Modi | Fertig | Real-World zwingt Spezies `mensch` |
| Kampagnen/Szenen/Posts | Fertig | IC/OOC getrennt, Moderation, Revisionen |
| GM-only Proben im Post | Fertig | Anlass/Held/Probe-Eigenschaft/Modifikator + Ergebnis im GM-Post |
| Probe-Erfolg automatisch | Fertig | Erfolg/Nicht-Erfolg wird technisch aus (Wurf + Modifikator) <= Zielwert berechnet |
| Proben-Persistenz auf Zielcharakter | Fertig | LE/AE-Impact wird gespeichert, LE-Schaden wird um Ruestungsschutz (RS) reduziert |
| GM-Inventar-Fund im Post | Fertig | Ziel-Held, Gegenstand, Menge und optional ausgeruestet werden direkt gebucht |
| GM-Inventar-Schnellaktion in Szene | Fertig | Add/Remove inkl. Menge direkt in Szenenansicht ohne Charakterbogen |
| Inventar-Audit-Log | Fertig | Jede Inventar-Aenderung speichert wer/wann/was (inkl. Quelle) |
| Benachrichtigungen | Fertig | In-App + optional Mail |
| Gamification (Punkte) | Fertig | post-basierte Punkteevents |
| Wissenszentrum | Fertig | HowTo, Regeln, Enzyklopaedie mit Such-/Kategorie-Filter, Card-Index und Detailseiten |
| Enzyklopaedie Admin | Fertig | GM/Admin CRUD Kategorien + Eintraege |
| PWA Basis | Teilweise fertig | Offline-Lesen + Queue aktiv, Push spaeter |
| Push Notifications | Geplant | nach Beta-Phase |

## Wichtige technische Entscheidungen
- Alle Tooling- und Deploy-Kommandos auf Server mit Plesk-PHP 8.5 ausfuehren:
  `/opt/plesk/php/8.5/bin/php artisan ...`
- Charakterformular hat einen robusten globalen Bootstrap-Fallback (`public/js/character-sheet.global.js`), der automatisch aus `resources/js/character-sheet.js` synchronisiert wird.
- Service Worker Caches wurden auf `v6` angehoben; Build-Assets (`/build/*`) werden `networkFirst` geladen.
- Finding 1 geschlossen: `effective_attributes` ist jetzt in Model, Request und Formular konsistent (nur Spezies-Modifikatoren, keine zusaetzlichen Berufungsboni in der Effektiv-Anzeige).
- Deutsche Validation-Locales sind hinterlegt (`lang/de/*`), damit im UI keine Roh-Keys wie `validation.min.string` mehr erscheinen.
- Navigations-Counter im Auth-Layout laufen zentral ueber `NavigationCounters` (aggregierte Count-Query statt mehrerer Einzelqueries).
- Probe-Persistenz ist transaktionsgesichert mit `lockForUpdate` auf dem Zielcharakter (sauberere LE/AE-Konsistenz bei zeitnahen GM-Proben).
- Plesk-Deploy-Script prueft auf vorhandenen Frontend-Build (`public/build/manifest.json`) und bricht bei fehlenden Artefakten frueh ab.
- Proben speichern Eigenschaft, Zielwert und Bestanden/Nicht-bestanden-Status in `dice_rolls`; Erfolg wird automatisch aus `(Wurf + Modifikator) <= Zielwert` berechnet.
- Charaktere speichern zusaetzlich `inventory` und `weapons` als JSON.
- Inventar-Eintraege sind stack-basiert (`name`, `quantity`, `equipped`) und werden beim Speichern normalisiert.
- Alle Inventar-Aenderungen werden in `character_inventory_logs` auditiert (Character-Form, GM-Post-Fund, Szenen-Schnellaktion).
- Ruestungen werden als JSON (`armors`) gespeichert; RS aus ausgeruesteten Ruestungen reduziert LE-Schaden bei GM-Proben.
- Astralenergie wird nur bei Magiebegabung vergeben (z. B. magische Spezies/Berufung); nicht-magische Menschen starten mit `AE=0`.
- Enzyklopaedie-Eintraege haben optional `game_relevance` (JSON) fuer LE/RS/AE/Probe/Real-World-Hinweise.
- Oeffentliche Enzyklopaedie unterstuetzt jetzt Detailroute pro Eintrag: `/wissen/enzyklopaedie/{kategorie}/{slug}`.
- Enzyklopaedie-Inhalte werden ueber `EncyclopediaContentRenderer` als bereinigtes Markdown gerendert (`html_input=strip`, `allow_unsafe_links=false`).

## Versionierungs-Regel (verbindlich)
- Laufende Instanz: `APP_VERSION` in `.env` setzen.
- Repo-Vorlage fuer neue Umgebungen: `APP_VERSION` in `.env.example` aktuell halten.
- Optional: `APP_BUILD` fuer Commit/Build-Kennung nutzen.
- Nach Versionsaenderung auf Server:
  `php artisan optimize:clear` und `php artisan config:cache`.

## Offene Prioritaeten
- PWA Push-Benachrichtigungen planen (nach Beta-Stabilisierung).
- Optional: weitere Konsolidierung von Legacy-Doku.
