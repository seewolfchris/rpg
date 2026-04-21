# Changelog

Alle nennenswerten Produktaenderungen an C76-RPG.

## v0.30-beta (2026-04-21)
- Privacy-first SL-Kontakt V1 eingefuehrt: kampagnengebundene Threads/Messages mit separatem Kontext (`CampaignGmContactThread`, `CampaignGmContactMessage`) statt Reuse des Post-/Scene-Write-Flows.
- Sichtbarkeit und Antwortrechte policy-first gehaertet: nur Thread-Ersteller, Campaign-Owner, akzeptierte `co_gm` und `admin`; globale GM-Rolle ohne Kampagnenbezug bleibt ausgeschlossen.
- Notifications fuer SL-Kontakte auf database-only erweitert (`kind=campaign_gm_contact_message`) und Empfaenger strikt pro Richtung begrenzt (Spieler -> Owner+Co-GMs, GM-Seite -> Thread-Ersteller) inkl. Self-Notification-Schutz.
- HTMX-Panel nur in `campaigns.show` integriert (kein Dashboard-/Inbox-Flow), inklusive Status-Labels und Szenentitel in der Erstellung.

## v0.29-beta (2026-04-12)
- Enzyklopaedie-Workflow PR-1 umgesetzt: Community-Vorschlaege als `pending`, Review fuer `gm/admin` und weltbezogene `co_gm`, schlanke Entry-Revision-Snapshots bei Proposal-Updates, neue Vorschlag-/Moderationsrouten und minimale Workflow-Views.
- Oeffentlicher Enzyklopaediepfad bleibt strikt `published`-only.

## v0.28-beta (2026-04-10)
- Security-Hardening fuer Offline-Queue und Preview abgeschlossen.
- Keine sensiblen Keys in Queue-Payload, Same-Origin-POST-Zwang, transientes CSRF-Re-Signing, clientseitig gehaertete Preview-Sanitization.
- Character-World-Scope gegen Cross-World-Umbuchung abgesichert, Header-/Request-Id-Haertung nachgezogen.

## v0.28-beta (2026-04-04)
- Release-Welle A/B/C abgeschlossen.
- Produktkonforme Composer-Metadaten (`c76/rpg`), zentrale Security-Header-Middleware, modulares Auth-Routing ohne Vertragsdrift.
- Architektur-Guardrails, MySQL-Critical-Gates, standardisiertes Domain-Event-Logging und Doku-Konsolidierung.

## v0.27-beta (2026-04-03)
- Hardening-Nachzug mit konsolidiertem `CampaignParticipantResolver` entlang Requests/Domain-Services.
- Gehaertete Post-/World-Invarianten (inkl. Default-Welt-Loeschschutz), atomares Invite-Upsert (`1062`-Fallback), separater CI-MySQL-Concurrency-Job.

## v0.27-beta (2026-04-02)
- Hardening-Release mit atomarem Post-Update-Flow, PWA-Privacy-Boundary bei Auth-Wechseln, idempotentem Reaction-Upsert.
- Kanonischer Invite-Weltkontext, Redis-Produktionsdefaults, scope-korrekter GM-/Dashboard-Count.
- Paginiertes Notification-Center, gesplittete Web-Routen, modularisierte Authorization-Matrix.
- Browserbasierte Playwright-E2E-Flows fuer Offline/Auth-Queue-Retry.

## v0.26-beta (2026-04-02)
- Architektur-Konsolidierung nach Multi-Welt-Rollout.
- d100-Probenpfad vereinheitlicht, Moderations-Readlogik dedupliziert.
- Character-/Progression-Autorisierung policy-first konsolidiert.
- Bookmark-Jump-Querylast reduziert.
- Character-Actions auf request-freie Inputs umgestellt, Payload-Typisierung bis inkl. Request-Grenze nachgezogen.
- PHPStan-Baseline auf null reduziert.

## v0.25-beta (2026-03-29)
- Immersion-Upgrade Phase 1-4 abgeschlossen.
- World-Theme-Resolver + CSS-Variablen am Root.
- Erweiterter Romanmodus inkl. Fullscreen/Progress-Lesezeichen/Shortcut-Navigation.
- Hero-/Card-Parallax-light mit reduced-motion-Fallback.
- PWA-Offline-/Queue-Narrativ und DE-first Sprachkonsistenz.

## v0.24-beta (2026-03-23)
- Finaler Immersion-Polish: Landing-Hero mit Szenen-Teaser, romanhafter Thread-Lesemodus, konsistente World/Character-Cards.
- Stability-Update: harte Service-Invarianten fuer Probe/Inventar.
- Robuste Atomic-/Compensation-Semantik in `StorePostService` und `CreateCharacterAction`.
- Queue-Retry-Jobs fuer fehlgeschlagene Szenen-/Mention-Benachrichtigungen.
