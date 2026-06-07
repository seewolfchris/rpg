# Produkt-Kommandostand

Stand der Analyse: 2026-06-07  
Arbeitsmodus: Repository-Analyse ohne Codeaenderungen, ohne neue Features, ohne Refactoring.

## Einordnung und Unsicherheiten

- Diese Bewertung ist aus dem lokalen Repository abgeleitet. Sie behauptet keinen aktuellen Zustand der produktiven Instanz.
- Der produktive Live-Stand ist laut `docs/STATUS.md` unbekannt und extern zu verifizieren.
- UI-Ueberladung und unklare Nutzerfuehrung sind aus Routen, Views, Tests und Dokumentation abgeleitet. Es wurde kein neuer Browser-/Usability-Test durchgefuehrt.
- Das Repository belegt ein Laravel-/Blade-/HTMX-Produkt mit Webroot `public/`, siehe `README.md`, `routes/web.php`, `resources/views/*`, `public/*` und `docs/DEPLOYMENT.md`.
- Grobe Bestandsmasse aus dem lokalen Repo: ca. 400 PHP-Dateien in `app/`, 110 Blade-Views, 231 Testdateien und 72 Migrationen.

## 1. Kurzbeschreibung des Projekts in 5 Saetzen

C76-RPG ist eine deutschsprachige, privacy-first Play-by-Post-Plattform fuer asynchrones, story-fokussiertes Rollenspiel. Das Produkt organisiert Spiel in Welten, Kampagnen, Szenen, Charakteren und IC/OOC-Beitraegen statt als Echtzeit-Chat oder generisches Forum. Der Stack ist bewusst serverseitig schlank gehalten: Laravel 12, Blade, HTMX, Alpine.js, Tailwind/Vite, MySQL/MariaDB und Redis als Produktionsziel. Der aktuelle Produktstatus ist Beta beziehungsweise kontrollierter Testbetrieb, waehrend der produktive Live-Stand aus dem Repo nicht belegbar ist. Der naechste sinnvolle Produktfokus ist nicht Feature-Fuelle, sondern ein verstaendlicher Testflight mit 3 bis 5 echten Nutzern.

Belege: `README.md`, `docs/STATUS.md`, `docs/PRODUCT_ROADMAP.md`, `docs/TESTFLIGHT_PLAN.md`, `composer.json`, `package.json`.

## 2. Zielgruppe

- Primaere Nutzer: Spieler und Spielleitungen in kleinen bis mittleren story-orientierten Play-by-Post-Runden.
- Neue Spieler: Menschen, die mit einem kompakten Einstieg Welt, Einladung, Charakter, Szene, IC/OOC und ersten Beitrag verstehen muessen.
- Spielleitungen: Personen, die Kampagnen vorbereiten, Szenen strukturieren, Spieler einladen, Posts moderieren, Handouts freigeben, Story-Log pflegen und Rueckfragen bearbeiten.
- Betreiber/Admins: kleine Community-Betreiber, die Rollen, Freischaltungen, Welten, Betrieb, Privacy und kontrollierten Testbetrieb verwalten.
- Nicht-Zielgruppe: Echtzeit-Chat-RPG, taktische Battlemap-Nutzung, generischer Forenersatz, SPA-first-Produkt, Mobile-App-Produktkern.

Belege: `README.md`, `docs/PRODUCT_ROADMAP.md`, `ROADMAP.md`, `docs/TESTFLIGHT_PLAN.md`.

## 3. Wichtigster Nutzerfluss fuer einen neuen Spieler

1. Registrierung oder Einladung erhalten. Registrierung erzeugt zunaechst einen `pending` Account, der freigeschaltet werden muss.
2. Nach Freischaltung einloggen und im Dashboard die aktive Welt und die naechste Aktion erkennen.
3. Offene Kampagneneinladung annehmen oder eine sichtbare Kampagne in der aktiven Welt oeffnen.
4. Einen Charakter in derselben Welt erstellen oder auswaehlen.
5. Kampagne oeffnen, Szene lesen, Handouts/Chronik nur bei Bedarf beachten.
6. Im Szenenthread einen kurzen IC-Beitrag schreiben, OOC nur fuer knappe Meta-Absprachen nutzen.
7. Danach ueber Dashboard, Szenen-Abos, ungelesene Posts oder Lesezeichen wieder einsteigen.

Belege: `routes/web/guest.php`, `app/Actions/Auth/RegisterUserAction.php`, `app/Http/Middleware/EnsureActiveAccount.php`, `resources/views/auth/account-status.blade.php`, `app/Actions/Dashboard/BuildDashboardNextStepAction.php`, `resources/views/dashboard.blade.php`, `resources/views/knowledge/getting-started.blade.php`, `resources/views/characters/partials/form.blade.php`, `resources/views/scenes/show.blade.php`, `resources/views/posts/_form.blade.php`.

Unsicherheit: Ob ein voellig neuer Spieler ohne externe Einladung realistisch sinnvoll startet, haengt von vorhandenen oeffentlichen Kampagnen und Freischaltung ab. Das Repo belegt die Mechanik, nicht die tatsaechlich vorhandenen Inhalte.

## 4. Wichtigster Nutzerfluss fuer eine Spielleitung

1. Account mit Kampagnenerstellungsrecht oder Admin-Recht erhalten.
2. Welt auswaehlen und Kampagne anlegen oder bestehende Kampagne oeffnen.
3. Szene erstellen, Beschreibung, Status, OOC-Erlaubnis, Bilder und Anschluss an vorherige Szene setzen.
4. Spieler einladen, Membership-Rollen setzen und offene Einladungen verwalten.
5. Szene bespielen: als Spielleitung posten, Spielerbeitraege moderieren, Pins setzen, Proben/Inventaraenderungen ausloesen.
6. Handouts, Story-Log, private Notizen und SL-Kontakte in der Kampagne beziehungsweise Szene nutzen.
7. Ueber Dashboard/Gm-Bereich offene Moderation und Charakterentwicklung kontrollieren.

Belege: `app/Policies/CampaignPolicy.php`, `app/Domain/Campaign/CampaignAccess.php`, `resources/views/campaigns/show.blade.php`, `routes/web/world/campaigns.php`, `routes/web/world/posts.php`, `resources/views/scenes/show.blade.php`, `resources/views/posts/_form.blade.php`, `resources/views/gm/index.blade.php`, `resources/views/gm/moderation.blade.php`, `resources/views/gm/progression.blade.php`.

Unsicherheit: Ein dediziertes "SL-Cockpit light" ist in der Produkt-Roadmap als Ziel benannt, aber der heutige Repo-Zustand wirkt eher wie verteilte Werkzeuge plus Kampagnen-/Szenenseiten.

## 5. Welche Funktionen existieren bereits?

- Auth: Registrierung, Login, Passwort-Reset, Logout, Accountstatus `pending/active/suspended`.
- Admin: Benutzerfreischaltung, Sperrung/Reaktivierung, Plattformrolle, Flags `can_create_campaigns` und `can_post_without_moderation`.
- Multi-World: Weltkatalog, Weltprofil, aktive Welt im Session-Kontext, weltgebundene Routen unter `/w/{world}/...`.
- Wissenszentrum: globale und weltgebundene Wissensseiten, Erste Schritte, Wie spielt man, Regelwerk, Enzyklopaedie.
- Kampagnen: CRUD, Sichtbarkeit, Status, Owner, Memberships, Einladungen, Rollen `gm`, `trusted_player`, `player`.
- Szenen: CRUD, Status, Reihenfolge, Beschreibung, Header-/Inhaltsbilder, Stimmung, vorherige Szene, OOC-Erlaubnis.
- Posts: IC/OOC, Charakter- oder Spielleitungsmodus, Markdown/BBCode/Klartext, Spoiler, optionales IC-Zitat, Edit-Historie, Moderation, Pins.
- Lesen: Szenenthread, IC-Lesefluss, OOC-Metakanal, Romanmodus, Lesepunkte, erster ungelesener Post, neuester Post.
- Charaktere: Index, Create/Edit/Show, Wizard fuer Erstellung, Weltoptionen, Attribute, Biografie, Spezies/Berufung, Inventar, Waffen, Ruestung, Avatar, Progression.
- Spielleitungswerkzeuge: Moderationsqueue, GM-Proben, LE/AE-Auswirkungen, Inventar-Schnellaktion, Charakterentwicklung.
- Kampagnenwerkzeuge: Handouts mit Reveal/Unreveal und kontrollierter Datei-Auslieferung, Story-Log, private Spielernotizen, SL-Kontakt-Threads.
- Benachrichtigungen: Mitteilungszentrale, Praeferenzen, Web Push, Szenen-Abos, Einladungen, Erwaehnungen hinter Flag.
- PWA/Offline: Manifest, Service Worker, Offline-Lesen fuer definierte Pfade, Offline-Post-Queue, Privacy-Boundary/Logout-Cleanup.
- Gamification: Punkte und Rangliste.
- Feature-Flag-Bereiche: Kampf-/Magie-/Konfliktwerkzeuge, Editor-Live-Preview, Draft-Autosave, Reactions, Active Characters Week, Markdown-Weltcontent-Vorschau.
- Betrieb/Qualitaet: CI, PHPStan, Architektur-Guardrails, Feature-/Unit-/E2E-/JS-Tests, Release-Smoke, Deployment- und Operations-Doku.

Belege: `routes/web/*.php`, `routes/web/world/*.php`, `app/Actions/*`, `app/Domain/*`, `app/Policies/*`, `resources/views/*`, `config/features.php`, `docs/PWA_OFFLINE.md`, `.github/workflows/ci.yml`.

## 6. Welche Funktionen sind fuer einen Testflight zwingend noetig?

- Freischaltung und Login muessen fuer alle Testnutzer funktionieren.
- Eine vorbereitete Testwelt, Testkampagne und mindestens eine offene Szene muessen existieren.
- Einladungen beziehungsweise Kampagnenzugang muessen eindeutig auffindbar und funktional sein.
- Charaktererstellung muss fuer neue Spieler ohne persoenliche Schritt-fuer-Schritt-Erklaerung abschliessbar sein.
- Spieler muessen Szene lesen, IC/OOC verstehen und einen Beitrag speichern koennen.
- Spielleitung muss Beitraege moderieren und sinnvoll darauf reagieren koennen.
- Mindestens ein Beispiel-Handout und ein Story-Log-Eintrag sollten vorhanden sein, damit die Nebenwerkzeuge im Kontext getestet werden.
- Dashboard oder Wissenszentrum muss klar sagen, was der naechste Schritt ist.
- Ein Feedbackkanal muss bereitstehen. Laut `docs/TESTFLIGHT_PLAN.md` kann das intern, als SL-Kontakt, OOC-Thread oder extern erfolgen.
- Vor Start muessen Smoke-Check, produktiver Stand, Backup und Notfallkontakt geklaert sein. Der konkrete Live-Stand ist aus dem Repo nicht belegbar.

Belege: `docs/TESTFLIGHT_PLAN.md`, `app/Actions/Dashboard/BuildDashboardNextStepAction.php`, `tests/Feature/DashboardNextStepTest.php`, `tests/Feature/CampaignScenePostWorkflowTest.php`, `tests/Feature/HandoutManagementTest.php`, `tests/Feature/StoryLogManagementTest.php`, `docs/OPERATIONS_RUNBOOK.md`.

## 7. Welche Funktionen sind aktuell eher Ballast oder zu frueh?

- Kampf-/Magie-/Konfliktwerkzeuge: implementiert, aber hinter `COMBAT_TOOLS_ENABLED`; fuer Testflight nicht Kern des ersten Spielerlebnisses.
- Reactions, Mentions und Active Characters Week: in `config/features.php` als Wave-4-Features hinter Flags angelegt; fuer Testflight nicht prioritaer.
- Editor-Live-Preview und Draft-Autosave: hinter Wave-3-Flags; nuetzlich, aber nicht vor Grundverstaendnis.
- Rangliste/Punkte: kann motivieren, konkurriert aber im Dashboard mit "naechste Aktion".
- Voll ausgebaute Enzyklopaedie-/Admin-Redaktion: wertvoll fuer Langfristbetrieb, aber fuer den ersten Spieltest nur als Nachschlagewerk noetig.
- Breiter Ausbau von Handouts, Story-Log und Notizen: fuer Testflight reichen einfache Beispiele und klare Einbindung.
- Weitere Landingpage-Erweiterungen: die Landing-IA ist bereits umfangreich abgesichert; wichtiger ist der eingeloggte Spielfluss.
- Neue Architektur-, SPA-, Plugin-, Mobile-App- oder Battlemap-Richtungen: ausdrueckliche Nicht-Ziele in Roadmap und README.

Belege: `config/features.php`, `docs/PRODUCT_ROADMAP.md`, `ROADMAP.md`, `resources/views/dashboard.blade.php`, `tests/Feature/HomeLandingInformationArchitectureTest.php`.

## 8. Wo ist die Nutzerfuehrung wahrscheinlich unklar?

- Registrierung endet in `pending`; die Statusseite erklaert Warten/Freischaltung, bietet aber aus Repo-Sicht keinen direkten Support- oder Erwartungszeitpunkt.
- Weltkontext ist zentral, aber Spieler muessen verstehen, dass Kampagne, Wissen und Charakter zur gleichen aktiven Welt gehoeren.
- Das Dashboard hat eine starke Next-Step-Karte, zeigt daneben aber Tutorial, Weltwechsel, Feature-Kacheln, Punkte, Topliste, Abos und Bookmarks.
- Nach Charaktererstellung fuehrt der Code zur Charakterdetailseite. Unsicher ist, ob neue Spieler danach automatisch genug Richtung zur Kampagne oder Szene bekommen.
- Der Unterschied zwischen oeffentlicher Kampagne, Einladung, Membership und Schreibberechtigung ist fachlich sauber, aber fuer Nutzer wahrscheinlich nicht selbsterklaerend.
- Das Szeneninterface markiert neue Posts beim Oeffnen als gelesen und zeigt mehrere Lesepunkt-Aktionen. Das kann fuer Testnutzer ueberraschend sein.
- IC/OOC, Formatwahl, Charakterauswahl, Spielleitungsmodus und optionales Zitat liegen im selben Postformular. Fuer Spieler ist nur ein Teil davon relevant.
- Handouts, Chronik, Notizen und SL-Kontakte sind als Produktbegriffe sinnvoll, aber im Erstfluss nicht automatisch priorisiert.

Belege: `resources/views/auth/account-status.blade.php`, `resources/views/dashboard.blade.php`, `app/Actions/Dashboard/BuildDashboardNextStepAction.php`, `resources/views/knowledge/getting-started.blade.php`, `resources/views/characters/partials/form.blade.php`, `resources/views/campaigns/show.blade.php`, `resources/views/scenes/show.blade.php`, `resources/views/posts/_form.blade.php`.

## 9. Welche UI-Bereiche wirken vermutlich ueberladen?

- Dashboard: Begruessung, Punkte, Moderationsbadge, Next-Step, aktive Welt, Tutorial, fuenf Feature-Kacheln, ungelesene Szenen, Bookmarks und Top-Chronisten.
- Hauptnavigation: Welten, Wissen, Dashboard, Kampagnen, Charaktere, Mitteilungen, Rangliste, Abos, Lesezeichen, Einladungen, GM-Bereich, Benutzerverwaltung, Punkte, PWA, Logout.
- Kampagnenseite: Kampagnenbeschreibung, Szenenfilter, Handouts, Chronik, Notizen, Szene anlegen, Teilnehmer, Rollen, SL-Kontakte und Einladungsverwaltung in einer langen Seite.
- Szenenseite: Header, Statusbadges, vorherige Szene, Beschreibung/Bilder, Pins, Schnellnavigation, Abo-Aktionen, Bookmark-Formular, Handouts, Chronik, Notizen, GM-Inventar, Thread, Romanmodus und Beitragserstellung.
- Postformular: fuer Spieler und Spielleitung gemeinsam gebaut; der SL-Teil mit Bildern, Proben, LE/AE und Inventarfund kann die einfache Aufgabe "Beitrag schreiben" ueberstrahlen, auch wenn vieles rollenbedingt ausgeblendet wird.
- Charakterformular: Wizard ist positiv, aber fachlich umfangreich durch Welt, Herkunft, Spezies, Berufung, Attribute, Biografie, Geheimnisse, Ausruestung und Avatar.
- Admin-Benutzerverwaltung: viele Steuerungen pro Tabellenzeile; fuer Admins tragbar, aber nicht testflight-kritisch.

Belege: `resources/views/dashboard.blade.php`, `resources/views/components/navigation/global.blade.php`, `resources/views/campaigns/show.blade.php`, `resources/views/scenes/show.blade.php`, `resources/views/posts/_form.blade.php`, `resources/views/characters/partials/form.blade.php`, `resources/views/admin/users/moderation.blade.php`.

## 10. Welche technischen Bereiche sind kritisch?

- Status- und Dokumentationsdrift: `docs/STATUS.md` ist kanonisch; Live-/Build-Stand darf nicht aus `main` abgeleitet werden.
- Rechte- und Weltkontext-Invarianten: Multi-World, Campaign-Memberships, Public/Private-Sichtbarkeit und Policies muessen konsistent bleiben.
- Post-Hotpath: Szenen, Posts, Moderation, Pins, Revisions, Notifications, Read-Tracking und Bookmarks greifen eng ineinander.
- Einladungs-/Membership-Lifecycle: private Kampagnen, Rollenwechsel und Revocation sind fachlich sensibel.
- Offline/PWA/Privacy-Boundary: IndexedDB-Queue, CSRF-Re-Signing, Logout-Cleanup und Auth-Wechsel sind sicherheits- und vertrauensrelevant.
- Web Push und Queue-Retry: Produktion setzt Redis, Queue-Worker, VAPID und strukturiertes Fehlerhandling voraus.
- Medien-Privacy-Grenze: immersive Post-Bilder und Szenen-Inhaltsbilder liegen auf Public-Disk; vertrauliche Medien muessen Handouts sein.
- Character-Create/Update: Weltoptionen, Attribute, Inventory, Avatar-Handling und Progression sind datenreich.
- Feature-Flags: Kampf/Magie, Wave-3/Wave-4 und Markdown-Vorschau sollten nicht versehentlich in Testflight-Komplexitaet kippen.
- Build-/Asset-Drift: `public/build` und `public/js/character-sheet.global.js` sind laut CI/Release-Check explizit zu pruefen.

Belege: `docs/STATUS.md`, `docs/ARCHITECTURE.md`, `docs/TECHNICAL_ROADMAP.md`, `docs/PWA_OFFLINE.md`, `docs/SECURITY.md`, `docs/OPERATIONS_RUNBOOK.md`, `.github/workflows/ci.yml`, `app/Domain/Campaign/CampaignAccess.php`, `app/Policies/*`, `app/Domain/Post/*`, `app/Domain/Scene/*`.

## 11. Welche Dokumentation ist aktuell massgeblich?

- Kanonischer Status: `docs/STATUS.md`.
- Einstieg, Setup und Kernbeschreibung: `README.md`.
- Doku-Wegweiser: `docs/README.md`.
- Strategische Roadmap: `ROADMAP.md`.
- Produktprioritaeten: `docs/PRODUCT_ROADMAP.md`.
- Technische Konsolidierung: `docs/TECHNICAL_ROADMAP.md`.
- Testflight: `docs/TESTFLIGHT_PLAN.md`.
- Architekturstandard: `docs/ARCHITECTURE.md`.
- Release-Flow und Gates: `docs/RELEASE-CHECKLISTE.md`.
- Betrieb und Incidents: `docs/OPERATIONS_RUNBOOK.md`.
- Deployment: `docs/DEPLOYMENT.md`.
- PWA/Offline: `docs/PWA_OFFLINE.md`.
- Technische Security: `docs/SECURITY.md`.
- Content-Stand: `docs/CONTENT-STATUS.md`.
- ADRs fuer zentrale Entscheidungen: `docs/adr/*`.

Hinweis: Historische Smoke-, Performance- und Audit-Dokumente sind nuetzliche Referenzen, aber nicht automatisch aktueller Produktstand.

## 12. Was sollte als naechstes NICHT gemacht werden?

- Keine neue grosse Feature-Welle vor einem echten Testflight.
- Keine SPA-/Framework-Neuausrichtung.
- Keine taktische Battlemap, keine Spieler-Kampfqueue, keine Echtzeit-/WebSocket-Kernarchitektur.
- Keine weitere Verdichtung der Szenenseite mit neuen Panels.
- Keine Erweiterung der Ranglisten-/Community-Features vor Klarheit im Erstfluss.
- Keine neuen komplexen Editorfunktionen, solange IC/OOC, Charakterwahl und Speichern fuer Neulinge nicht validiert sind.
- Kein Ausbau von Kampf/Magie als Produktkern fuer den Testflight.
- Keine zweite Statusquelle fuer Release, Build oder Live-Stand.
- Keine grossen Architektur-Refactors an Post-, Einladung-, Offline-, WebPush- oder Medienpfaden ohne Charakterisierungstests und konkreten Anlass.
- Keine vertraulichen Medien in Public-Inline-Bildpfade verschieben.

Belege: `ROADMAP.md`, `docs/PRODUCT_ROADMAP.md`, `docs/TECHNICAL_ROADMAP.md`, `docs/STATUS.md`, `docs/SECURITY.md`.

## 13. Die naechsten 10 Aufgaben, priorisiert nach Wirkung auf das Benutzererlebnis

1. Testflight-Skript konkretisieren und ausfuehren: 1 Spielleitung, 3 bis 5 Spieler, vorbereitete Kampagne, Szene, Charakterweg, Handout, Story-Log, Feedbackkanal.
2. Dashboard auf "eine naechste Aktion" fuer neue Spieler schaerfen: Next-Step-Karte visuell und inhaltlich ueber Tutorial, Punkte und Nebenbereiche stellen.
3. Nach Charaktererstellung klar weiterleiten oder klar verlinken: naechste sichtbare Kampagne, offene Einladung oder aktuelle Szene.
4. Szenenseite fuer Spieler mental entlasten: wichtigste Aktionen "lesen", "erster ungelesener Post", "antworten" staerker priorisieren; Abo/Bookmark/Notizen/Chronik sekundaer machen.
5. Postformular fuer Spieler vereinfachen: Standardpfad "IC als Charakter in Markdown" klar hervorheben; seltene Optionen weniger dominant machen.
6. Kampagnenseite rollenbasiert schaerfen: Spieler sehen zuerst Szenen und naechste Szene; Leitungs-/Einladungs-/Rollenwerkzeuge bleiben fuer SL erreichbar, aber weniger gemischt.
7. Accountstatus/Freischaltung erklaeren: erwarteter Ablauf und Kontakt-/Rueckfrageweg fuer pending Nutzer dokumentieren oder in UI anzeigen.
8. Feedbackkanal fuer Testflight bereitstellen: Seitenkontext, Rolle, Problem, Erwartung, tatsaechliches Verhalten und Screenshot optional erfassen.
9. Testflight-Smoke dokumentieren: vor Start `docs/STATUS.md`-Logik beachten, Smoke/Backup/Version extern verifizieren und keine Live-Behauptung aus `main` ableiten.
10. Ballast hinter Flags lassen: Kampf/Magie, Reactions, Editor-Preview, Draft-Autosave und Active-Characters nicht in den Testflight-Vordergrund holen.

Belege: `docs/TESTFLIGHT_PLAN.md`, `docs/PRODUCT_ROADMAP.md`, `resources/views/dashboard.blade.php`, `app/Actions/Dashboard/BuildDashboardNextStepAction.php`, `resources/views/scenes/show.blade.php`, `resources/views/posts/_form.blade.php`, `resources/views/campaigns/show.blade.php`, `config/features.php`.

## 14. Empfehlung: Was ist der eine naechste Schritt?

Der eine naechste Schritt ist ein kontrollierter Testflight mit minimaler Featureflaeche und beobachtetem Erstfluss: freigeschalteter Spieler, aktive Welt, Einladung oder sichtbare Kampagne, Charakter erstellen, Szene lesen, IC-Beitrag schreiben, SL reagiert/moderiert, Feedback erfassen.

Nicht zuerst weiterbauen. Zuerst messen, wo reale Testnutzer im vorhandenen Produkt stecken bleiben.

## Executive Summary

- Zustand: Beta-faehige, technisch breit abgesicherte Play-by-Post-Plattform mit vielen bereits implementierten Kernfunktionen und mehreren bewusst geflaggten Ausbaupfaden.
- Hauptproblem: Die Kernstrecke ist vorhanden, aber die UX konkurriert mit zu vielen parallelen Werkzeugen, Begriffen und Nebenaktionen, besonders auf Dashboard, Kampagnen- und Szenenseite.
- Empfehlung: Testflight jetzt mit klarer Minimalstrecke durchfuehren und danach nur die Top-UX-Hindernisse im Erstspielerfluss beheben.
