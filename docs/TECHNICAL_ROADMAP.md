# Technische Roadmap

## Zweck

Dieses Dokument beschreibt technische Konsolidierung, Wartbarkeit und
Betriebsstabilitaet. Es ist keine Release-Statusquelle. Aktueller Status steht
in `docs/STATUS.md`.

## Leitplanken

- Actions-first
- Thin Controller
- mutierende Actions final
- Status-Drift vermeiden
- Config-Drift sichtbar machen
- Multi-World-Invarianten schuetzen
- keine SPA-first-Neuausrichtung
- kein Realtime/WebSocket-Kern
- keine unnoetigen Dependencies
- kleine, reversible Scheiben

## Themenfelder

### Dokumentations- und Status-Drift

- `docs/STATUS.md` bleibt kanonisch
- Driftchecks erhalten
- keine Live-/Release-Daten in nicht-kanonischen Dokumenten
- EN-Summaries nicht als zweite Wahrheit fuehren

### Action-Layer

- mutierende Actions final halten
- read-only Allowlist pflegen
- Characterization Tests vor Konsolidierung
- keine riskanten Refactors an Concurrency-Pfaden

### Routing und Controller

- Controller duenn halten
- mutierende Route-Closures vermeiden
- World-Kontext konsistent halten
- modulare Routenstruktur beibehalten

### Redis, Queue, Cache, Session

- Produktionsdefaults klar halten
- Queue Worker als Produktionsannahme
- Redis fuer Queue/Cache/Session
- Retry- und WebPush-Pfade stabilisieren

### Medien und Storage

- Public-Disk-Grenze fuer Inline-Bilder dokumentiert halten
- vertrauliche Medien ueber kontrollierte Handouts
- Medien-Cleanup bei Szene/Kampagne/Post beachten
- keine globale Medienbibliothek als Schnellschuss

### Post-Domaene

- Post-Flow stabil halten
- Moderation, Revisionen, Pins, Reactions, Bookmarks nicht vermischen
- weitere Konsolidierung nur characterization-test-first

### Datenmodell-Haertung fuer User-Loeschung

- Leitregel: Benutzerkonten sind loeschbar. Erzaehlte Geschichte ist unverletzlich.
- Technischer Schuldschein: Narrative Inhalte duerfen auf Datenbankebene nicht mehr durch User-Cascade geloescht werden.
- Zielmodell: Restrict + Action. Direkte `User::delete()`-Pfade werden durch `restrictOnDelete()` blockiert; die Admin-Delete-Action haengt narrative Inhalte vorher kontrolliert auf `Geloeschter Benutzer <deleted-user@system.invalid>` um.
- Spaetere Migration fuer narrative User-FKs vorbereiten:
  - `posts.user_id`
  - `campaigns.owner_id`
  - `characters.user_id`
  - `scenes.created_by`
  - `story_log_entries.created_by`
  - `handouts.created_by`
  - `campaign_gm_contact_threads.created_by`
  - `campaign_gm_contact_messages.user_id`
  - `dice_rolls.user_id`
  - `post_mentions.mentioned_user_id`
- Persoenliche/technische Daten bleiben bewusst entfernbar, z. B. Memberships, Invitations, Bookmarks, Subscriptions, Reactions, Notes, Sessions und Push-Abos.
- Umsetzung erst nach erfolgreichem Deploy-Smoke der aktuellen Admin-User-Loeschung; Migration mit Tests fuer direkten Delete-Block und erfolgreichen Action-Reassign absichern.

### Betrieb

- Release-Smoke verbessern
- Build-Artefakt-Drift verhindern
- Admin-Gesundheitsseite vorbereiten
- APP_VERSION / APP_BUILD sichtbar machen
- Backup-/Restore-Dokumentation ausbauen

### Archivierung

- historische Audit-/Smoke-/Performance-Dokumente markieren
- alte Reports nicht als aktuellen Zustand darstellen
- `docs/archive/` als Ablage fuer historische Plaene nutzen

## Nicht-Ziele

- grosse Architektur-Neuschreibung
- neue Framework-Schicht
- SPA-Umbau
- Plugin-System
- WebSocket-/Realtime-Kern
- breiter Medienmanager
- taktische Kampfmaschine
