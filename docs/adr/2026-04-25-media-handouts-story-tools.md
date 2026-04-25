# ADR 2026-04-25: Media-, Handout- und Story-Tools-Erweiterung

## 1. Status
Proposed / Vorgeschlagen

## 2. Kontext
C76-RPG hat bereits ein starkes textbasiertes Fundament rund um `App\Models\Post` mit IC/OOC, Spielleitungsmodus, Moderation, Revisionen, Pins, Würfel-/Probenintegration und SoftDeletes.

Für längere Play-by-Post-Kampagnen reichen reine Textbeiträge jedoch nicht dauerhaft aus. Es fehlen strukturierte visuelle Referenzen, persistent auffindbare Handouts, kompakte Navigationshilfen und private Spielererinnerungen.

Ziel ist keine dekorative Erweiterung, sondern bessere Orientierung, stärkere Immersion und langfristige Nutzbarkeit über den gesamten Kampagnenverlauf.

## 3. Zielbild
Das Zielbild erweitert den bestehenden Szenenfluss um drei klar begrenzte Bausteine:
- Immersive Bilder in GM-Erzählbeiträgen innerhalb des Story-Feeds.
- Persistente Handouts als kampagnen- oder szenenbezogene Referenzobjekte.
- Ein schlankes Szenen-Toolpanel mit Tabs `Handouts`, `Story-Log` und `Meine Notizen`.

Der Story-Feed bleibt die primäre Nutzererfahrung. Eine mögliche Avatar-Migration wird als spätere optionale Phase eingeplant und ist nicht Teil der ersten Implementierungsphase.

## 4. Fachliche Trennung
### Post / `immersive_images`
- Gehört zum bestehenden Aggregat `App\Models\Post`.
- Dient für atmosphärische Inline-Bilder in GM-Erzählbeiträgen.
- Ist Teil des Story-Feeds und kein separates Inhaltsobjekt.
- Muss dieselben Sichtbarkeits-, Moderations- und SoftDelete-Semantiken wie der zugehörige Post einhalten.

### Handout
- Eigenständiges Domänenmodell.
- Persistentes Referenzobjekt auf Kampagnen- oder Szenenebene.
- Beispiele: Karten, Briefe, Siegel, Skizzen, Hinweise, Schiffspläne, Artefaktbilder.
- Überlebt einzelne Posts und kann unabhängig davon verwaltet werden.
- Unterstützt `unrevealed`/`revealed` als fachlichen Zustand.

### StoryLogEntry
- Eigenständiges Navigations-/Indexobjekt.
- Repräsentiert Kapitelmarker, Zusammenfassungen oder Story-Pivotpunkte.
- Wird initial manuell oder semimanuell gepflegt, nicht durch vollautomatische Markdown-Heading-Extraktion.

### PlayerNote
- Private Spielererinnerung.
- Gehört genau einem User.
- Optional auf Kampagne, Szene und/oder Charakter bezogen.
- Für GM nicht sichtbar, solange keine spätere explizite Sharing-Funktion eingeführt wird.

Es wird ausdrücklich mit `App\Models\Post` gearbeitet; ein `ScenePost`-Modell wird nicht eingeführt.

## 5. Vorgeschlagenes Datenmodell
Die folgenden Strukturen sind als High-Level-Zielbild für spätere Implementierungs-PRs gedacht. Diese ADR führt keine Migrationen ein.

### Handout (voraussichtliche Felder)
- `id`
- `campaign_id`
- `scene_id` nullable
- `created_by`
- `updated_by` nullable
- `title`
- `description` nullable
- `revealed_at` nullable
- `version` oder `version_label` nullable
- `sort_order` nullable
- `timestamps`
- SoftDeletes optional, Entscheidung in der Umsetzung

World-Kontext wird grundsätzlich über die Kampagne abgeleitet. Ein direktes `world_id` am Handout ist nur zu prüfen, wenn Query-/Indexanforderungen es später erzwingen.

### StoryLogEntry (voraussichtliche Felder)
- `id`
- `campaign_id`
- `scene_id` nullable
- `post_id` nullable
- `created_by`
- `title`
- `excerpt` nullable
- `sort_order`
- `occurred_at` nullable
- `timestamps`

### PlayerNote (voraussichtliche Felder)
- `id`
- `user_id`
- `campaign_id`
- `scene_id` nullable
- `character_id` nullable
- `title` nullable
- `body`
- `pinned` boolean
- `timestamps`
- SoftDeletes optional, Entscheidung in der Umsetzung

## 6. Media Library Strategy
Als geplante Grundlage wird Spatie Laravel Media Library vorgesehen. Diese ADR installiert die Abhängigkeit noch nicht.

Geplante Collections:
- `immersive_images` auf `App\Models\Post`
- `handout_file` oder `handout_images` auf `Handout`
- `avatar` auf `Character`/`User` später optional

Der aktuelle Avatar-Bestand bleibt in Phase 1 unverändert:
- `Character.avatar_path`
- `App\Services\Character\AvatarService`
- Speicherung auf `public`-Disk
- Rendering über `asset('storage/...')`

Die Legacy-Avatar-Logik wird in der ersten Implementierungsphase nicht geändert. Eine Avatar-Migration darf erst nach Stabilisierung von immersiven Bildern und Handouts erfolgen. Das erste Media-PR liefert nur die Paket-Fundierung, keine breite Medienmigration.

## 7. Storage and Privacy Decision
`public`-Disk ist operativ einfach, aber für unrevealed oder private Handouts ungeeignet. Für echte Privatsphäre ist private oder kontrollierte Auslieferung erforderlich.

Konservative Entscheidung:
- Unrevealed-Handouts dürfen nicht über öffentliche URLs erreichbar sein.
- Handout-Dateien müssen über autorisierte Laravel-Routen oder einen gleichwertigen kontrollierten Mechanismus ausgeliefert werden.
- Thumbnails/Conversions unrevealed Handouts dürfen nicht über öffentliche URLs leaken.
- Immersive Bilder dürfen initial einfacher starten, müssen aber Post-Sichtbarkeit, Moderation und SoftDelete-Semantik respektieren.

Die spätere Implementierung muss Lifecycle-Regeln explizit entscheiden für:
- Post-Delete
- Post-Restore
- Post-ForceDelete
- Scene-Delete/Archive
- Campaign-Delete/Archive
- Handout-Unreveal/Reveal
- Medienersetzung/Versionierung

## 8. Authorization Rules
- Kampagnen-Owner und Kampagnen-GM dürfen Handouts erstellen, ändern, löschen und revealen.
- Legacy-Begriffe wie „Co-GM“ können im UI erscheinen; die Durchsetzung folgt `Campaign::canManageCampaign()` und `Campaign::canModeratePosts()`.
- Trusted Player sind nicht automatisch Handout-Manager.
- Spieler dürfen nur revealed Handouts sehen, wenn sie auf die Kampagne zugreifen dürfen.
- Private PlayerNotes sind ausschließlich für den owning User sichtbar.
- GM darf private PlayerNotes nicht sehen, solange keine spätere explizite Sharing-Funktion existiert.
- HTMX-Endpunkte müssen dieselben Policies und World-Context-Guards wie Full-Page-Requests erzwingen.
- Direkte Media-Delivery-Routen müssen Zugriff vor Dateiauslieferung autorisieren.

## 9. UI Rollout
Desktop:
- Story-Feed bleibt primär.
- Ein schlankes rechtes Szenen-Toolpanel kann Tabs enthalten: `Handouts`, `Story-Log`, `Meine Notizen`.

Mobile:
- Das Toolpanel wird als einklappbarer Bereich oder gestapelte Tab-Fläche bei/unter den Thread-Werkzeugen dargestellt.
- Es darf den Story-Feed nicht dominieren.
- Es darf die Nutzbarkeit des Post-Formulars nicht verschlechtern.

Technische Leitplanken:
- Tab-Inhalte über HTMX-Fragmente.
- Progressive Enhancement statt SPA-Umbau.
- Bestehende Grenzen bleiben erhalten: Reading Mode, `scene-thread-feed`, `data-reading-mode-chrome`, Offline-Queue-Panels, Post-Form-Verhalten, GM-Inventar-Schnellaktion.

## 10. Implementation Sequence
### PR-0
- Nur ADR.

### PR-1
- Spatie Media Library Foundation hinzufügen.
- Keine Avatar-Migration.
- Keine player-facing Funktion.
- Konfiguration/Migration nur soweit für Paketsetup notwendig.
- Optional minimaler Smoke-/Architekturtest.

### PR-2
- Immersive Bilder für GM-Erzählposts auf `App\Models\Post`.
- Mehrfachbilder unterstützen.
- Post-Sichtbarkeit, Moderationsstatus und SoftDeletes respektieren.
- Keine unbeabsichtigte Exposition von pending/rejected/private Inhalten.

### PR-3
- `Handout`-Modell, Policy, FormRequests, Actions/Services und einfache GM-verwaltete Liste/Übersicht.
- Spieler sehen nur revealed Handouts.
- Unrevealed Medien dürfen nicht über öffentliche URLs oder Thumbnails leaken.

### PR-4
- Szenen-Toolpanel/Sidebar mit HTMX-Tabs:
  - Handouts
  - Story-Log
  - Meine Notizen
- Story-Feed bleibt primär.

### PR-5
- Manuelle StoryLogEntry-Erstellung/Markierung aus GM-Posts.
- Keine vollautomatische Markdown-Heading-Extraktion als Startpunkt.

### PR-6
- Private `PlayerNote`.
- Harte Privacy-Grenze: nur owning User.

### PR-7
- Optionale Avatar-Migration zu Spatie Media Library nach Stabilisierung der neuen Medienfeatures.

## 11. Risiken
- Leaks privater Medien.
- Leaks unrevealed Thumbnails/Conversions.
- Überdimensionierte Uploads.
- Unsichere Dateitypen (z. B. SVG).
- EXIF-/Standortmetadaten.
- Verwaiste Medienobjekte.
- Queue-/Conversion-Fehlerpfade.
- Unklare Retention bei SoftDelete/ForceDelete.
- Mobile Layout-Überladung.
- Controller-Bloat statt Action-/Service-Grenzen.
- N+1-Queries zwischen Thread und Toolpanel.
- Regressionen im bestehenden Reading Mode.
- Regressionen im Offline-Queue-Verhalten.
- Unklare Handout-Versionierung.
- Versehentliche GM-Einsicht in private PlayerNotes.
- Dauerhafte öffentliche Disk-URLs als unbeabsichtigte Zugriffspfade.

## 12. Non-Goals
- Keine Runtime-Implementierung in diesem ADR-PR.
- Keine Dependency-Installation in diesem PR.
- Keine Migrationen in diesem PR.
- Keine Avatar-Migration in der ersten Implementierungsphase.
- Keine KI-Bilderzeugung.
- Kein vollständiges DAM-/Media-Archiv-System.
- Kein SPA-Rewrite.
- Keine Echtzeit-Kollaboration.
- Kein öffentlicher Datei-Browser.
- Keine Einführung externer CDN-Pflicht.
- Keine automatische Markdown-Heading-Extraktion in der ersten StoryLog-Phase.
- Keine geteilten/kollaborativen PlayerNotes in der ersten PlayerNote-Phase.

## 13. Verification
Für dieses ADR-PR gilt:
- Keine Runtime-Verhaltensänderung.
- Keine Dependency-Änderung.
- Keine Migration.
- Keine Änderungen an Routes/Controller/Models/Views/Assets.
- `git diff` soll nur die neue ADR-Datei zeigen.

Für spätere Implementierungs-PRs gelten die normalen Gates:
- `composer validate --strict`
- `composer analyse`
- `php artisan test --without-tty --do-not-cache-result`
- `npm run test:js` (bei Frontend-Verhaltensänderungen)
- `npm run build` (bei Asset-Änderungen)
- Fokussierte Feature-Tests für Authorization, World-Context, Visibility und HTMX-Fragmente
