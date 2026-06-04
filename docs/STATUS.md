# STATUS - C76-RPG (kanonische Statusquelle)

Diese Datei ist die einzige kanonische Quelle fuer:
- Release-, Entwicklungs- und Live-Status
- operativen Gate-Stand
- letzten dokumentierten Release-Zeitpunkt
- Audit-Basis aktueller Dokumentationspruefungen

## Statusachsen

- Statusdatum: **2026-06-04**
- Produktstatus: **Beta (kontrollierte Nutzung/Testbetrieb; weitere Aenderungen moeglich)**
- Audit-Basis dieser Dokumentationspruefung: **`d442c0c`**
- Letztes veröffentlichtes Release: **`v0.32-beta` am 2026-06-03** (Quelle: `CHANGELOG.md`)
- Entwicklungsstand: **Branch `main`** (kein statischer Commit; konkrete Commits altern mit jedem Merge)
- Produktive Live-Instanz: **https://rpg.c76.org**
- Produktiver Live-Stand: **unbekannt / extern zu verifizieren**

Ein produktiver Commit darf hier nur genannt werden, wenn er ueber einen sichtbaren Build-Hinweis,
ein Deployment-Protokoll oder `APP_BUILD` tatsaechlich belegt ist. Der bestehende dokumentierte
Release- und Live-Stand ist `v0.32-beta`; post-release Aenderungen auf `main` sind nicht automatisch
als live zu behaupten.

## Integrationsstand (post-release)

- Rollenmodell-Reihe PR1-PR6 ist umgesetzt:
  - globale Plattformrolle effektiv `admin`/`player`; globales `gm` entfernt
  - Plattformflags `can_create_campaigns` und `can_post_without_moderation`
  - kampagnenbezogene Rollen ueber `campaign_memberships` (`gm`, `trusted_player`, `player`)
  - Kampagnen-Owner bleibt getrennt auf `campaigns.owner_id`
  - Kampagnenerstellung auf `admin || can_create_campaigns` umgestellt
  - Membership-first Lesepfade fuer Kampagne/Szene/Post
  - UI-Trennung: Admin-Bereich fuer Plattformrechte, Kampagnenbereich owner-only fuer Teilnehmerrollen
- UI-Fix SL-Kontakte: Kontaktformular oeffnet als viewportweiter Modal ueber `x-teleport="body"`.
- Lizenz-/Dokumentationsstand: proprietaer / all rights reserved; keine Open-Source-Nutzungserlaubnis.
- Letzter dokumentierter Stabilisierungs-/Auditlauf vor `v0.32-beta`: **2026-05-04**, Ergebnis **gruen**.
- Ein neuer vollstaendiger Release-Gate-Lauf fuer `v0.32-beta` ist in dieser Datei noch nicht protokolliert.

## Verifikations- und Gate-Stand

- Letzter dokumentierter Vollstand: **2026-04-04**
- Gesamtstatus dieses Vollstands: **gruen**
- Pflichtgates im Vollstand:
  - `scripts/check_status_drift.sh`
  - `composer validate --strict`
  - `composer analyse`
  - `php artisan test --without-tty --do-not-cache-result tests/Feature/Architecture/ArchitectureGuardrailsTest.php`
  - `php artisan test --without-tty --do-not-cache-result --exclude-group=mysql-concurrency --exclude-group=mysql-critical`
  - `php artisan test --without-tty --do-not-cache-result --group=mysql-concurrency` (CI-MySQL-Job)
  - `php artisan test --without-tty --do-not-cache-result --group=mysql-critical` (CI-MySQL-Job)
  - `npm run test:js`
  - `npm run test:e2e`
  - `npm run build`
  - `SMOKE_MODE=artisan SMOKE_START_SERVER=0 scripts/release_smoke.sh`
  - `git diff --exit-code -- public/build public/js/character-sheet.global.js`
- Letzter dokumentierter Stabilisierungs-/Auditlauf (2026-05-04):
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

- `docs/STATUS.md` bleibt kanonisch fuer Release-Status, Entwicklungsstand, Live-Stand, Build und Gate-Stand.
- README und ROADMAP duerfen nur knappe Orientierung enthalten und muessen auf diese Datei verweisen.
- Historische Release-Historie bleibt in `CHANGELOG.md`.
- Exakte Gate-Befehle bleiben in `docs/RELEASE-CHECKLISTE.md`.
