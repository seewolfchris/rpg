# STATUS - C76-RPG (kanonische Live-Quelle)

Diese Datei ist die einzige kanonische Quelle fuer:
- aktuelle Versionslinie
- operativen Gate-Stand
- letzten dokumentierten Release-Zeitpunkt

## Operativer Live-Status

- Statusdatum: **2026-05-01**
- Produktstatus: **Beta (aktiv entwickelt)**
- Versionslinie: **`v0.30-beta`**
- Letzter Release-Eintrag: **`v0.30-beta` am 2026-04-21** (Quelle: `CHANGELOG.md`)

## Integrationsstand (post-release)

- Media-/Handout-/Story-Tool-Reihe PR-0 bis PR-6 ist umgesetzt:
  - PR-0 ADR Media/Handouts/Story-Tools
  - PR-1 Spatie Media Library Foundation
  - PR-2 Immersive Bilder fuer GM-Erzaehlposts
  - PR-3 Persistente Campaign/Scene-Handouts
  - PR-3b Handout-Media-Vertrag gehaertet
  - PR-4a Szenen-Handout-Toolpanel
  - PR-4b Handout-UX-Polish
  - PR-5 Chronik / StoryLogEntry
  - UX-Fix Romanmodus: "beenden & antworten"-CTA
  - PR-6 Private Player Notes / Meine Notizen
- Letzter dokumentierter Stabilisierungs-/Auditlauf: **2026-05-01**, Ergebnis **gruen**.
- Audit-Ergebnis: **kein Runtime-Diff erforderlich**.

## Verifikations- und Gate-Stand

- Letzter dokumentierter Vollstand: **2026-04-04**
- Gesamtstatus dieses Vollstands: **gruen**
- Pflichtgates im Vollstand:
  - `php artisan test --without-tty --do-not-cache-result --exclude-group=mysql-concurrency --exclude-group=mysql-critical`
  - `php artisan test --without-tty --do-not-cache-result --group=mysql-concurrency` (CI-MySQL-Job)
  - `php artisan test --without-tty --do-not-cache-result --group=mysql-critical` (CI-MySQL-Job)
  - `node --test tests/js/*.mjs`
  - `npm run test:e2e`
  - `npm run build`
  - `composer analyse`
- Letzter dokumentierter Stabilisierungs-/Auditlauf (2026-05-01):
  - `composer validate --strict`
  - `composer analyse`
  - `php artisan test --without-tty --do-not-cache-result --filter=PostImmersiveImagesFeature`
  - `php artisan test --without-tty --do-not-cache-result --filter=Handout`
  - `php artisan test --without-tty --do-not-cache-result --filter=StoryLog`
  - `php artisan test --without-tty --do-not-cache-result --filter=PlayerNote`
  - `php artisan test --without-tty --do-not-cache-result --filter=SceneHandoutPanel`
  - `php artisan test --without-tty --do-not-cache-result --filter=SceneStoryLogPanel`
  - `php artisan test --without-tty --do-not-cache-result --filter=ScenePlayerNotePanel`
  - `php artisan test --without-tty --do-not-cache-result --filter=SceneReadingModeReplyCta`
  - `php artisan test --without-tty --do-not-cache-result --filter=CampaignScenePostWorkflow`
  - `php artisan test --without-tty --do-not-cache-result --filter=AuthorizationWorldContextMutationScope`
  - `php artisan test --without-tty --do-not-cache-result --filter=MutatingRoutesRateLimit`
  - `php artisan test --without-tty --do-not-cache-result --filter=CharacterProgression`
  - `npm run build`

## Pflege-Regel

- README und ROADMAP enthalten keine Live-Statuszahlen mehr.
- Historische Release-Historie bleibt in `CHANGELOG.md`.
- Exakte Gate-Befehle bleiben in `docs/RELEASE-CHECKLISTE.md`.
