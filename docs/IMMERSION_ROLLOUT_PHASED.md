# Immersion Rollout (Phased)

Status: abgeschlossen (Phasen A-C), Stand 2026-03-29.

Hinweis:
- Dieses Dokument bleibt als Rollout-Playbook erhalten.
- Für den aktuellen technischen Ist-Stand siehe `docs/IMMERSION-ARCHITEKTUR.md`.

## Ziel
Gestufter Release für Immersion-Features mit geringem Risiko:
- Phase A: DB + Welle 1/2 UI
- Phase B: Welle 3 Editor-Hilfen
- Phase C: Welle 4 Community-Features

## Feature-Flags
Konfiguration in `config/features.php`:
- `FEATURE_WAVE3_EDITOR_PREVIEW`
- `FEATURE_WAVE3_DRAFT_AUTOSAVE`
- `FEATURE_WAVE4_MENTIONS`
- `FEATURE_WAVE4_REACTIONS`
- `FEATURE_WAVE4_ACTIVE_CHARACTERS`

## Phase A (Release-Block 1): DB + Welle 1/2

### Scope
- DB-Migrationen für Szenen-/Charakter-Erweiterungen
- Relative Zeitstempel
- IC-first Thread-Layout mit OOC-Collapse-Default
- IC-Zitate in `posts.meta.ic_quote`
- Szenen-Mood, Headerbild, Szene-Verkettung

### Deploy-Reihenfolge (verbindlich)
1. Lokale Gates:
   - `composer analyse`
   - `php artisan test --without-tty --do-not-cache-result`
   - `node --test tests/js/*.mjs`
2. Deploy + Migration:
   - `php artisan migrate --force`
3. Flags für Phase A sicherstellen:
   - `FEATURE_WAVE3_EDITOR_PREVIEW=false`
   - `FEATURE_WAVE3_DRAFT_AUTOSAVE=false`
   - `FEATURE_WAVE4_MENTIONS=false`
   - `FEATURE_WAVE4_REACTIONS=false`
   - `FEATURE_WAVE4_ACTIVE_CHARACTERS=false`
4. Config neu laden:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
5. Phase-A-Smoke als hartes Gate:
   - `PHASE_A_BASE_URL="https://rpg.c76.org" PHASE_A_WORLD_SLUG="<world-slug>" PHASE_A_REPORT_OUT="docs/SMOKE-PHASE-A.md" scripts/release_phase_a_smoke.sh`

One-Command-Variante:
- `PHP_BIN=/opt/plesk/php/8.5/bin/php scripts/release_phase_a_flow.sh --base-url "https://rpg.c76.org" --world-slug "<world-slug>" --report-out "docs/SMOKE-PHASE-A.md"`
- Das Skript führt `migrate --force`, Cache-Refresh und danach das Phase-A-Gate in fixer Reihenfolge aus.
- Standard ist deploy-sicher: keine `artisan test`-Ausführung im Post-Deploy-Flow (`--run-test-gates` default `0`).
- `<world-slug>` entspricht einer aktiven Welt aus `/w/<world-slug>/...` oder kommt aus `WORLD_DEFAULT_SLUG`.
- Lokaler Vorab-Check mit Test-Gates:
  - `scripts/release_phase_a_flow.sh --smoke-mode artisan --skip-migrate --run-test-gates 1 --report-out "docs/SMOKE-PHASE-A-LOCAL.md"`

### Go/No-Go Kriterien
Go:
- Migration erfolgreich
- `scripts/release_phase_a_smoke.sh` exit code `0`
- Keine 5xx-Spitzen im Posting-Flow (`POST /w/{world}/campaigns/{campaign}/scenes/{scene}/posts`)
- Upload-Flow für Szenen-Header ohne auffällige Fehler

No-Go:
- Migration oder Smoke-Gate fehlgeschlagen
- Flag-Zustand nicht Phase-A-konform
- OOC-Collapse/IC-first bricht im Browser (JS-Fehler oder Persistenzfehler)

Sofortmaßnahmen bei No-Go:
1. Release stoppen, keine weiteren Flags aktivieren.
2. Ursache beheben und erneut deployen.
3. Falls bereits live: betroffene Features via Flag deaktivieren und Cache neu laden.

## Stabilitätsphase nach Phase A (3-5 Tage)

### Fokus
- Posting-Flow Stabilität
- OOC-Toggle-Persistenz pro Szene
- Upload-Handling für Headerbilder

### Tagesroutine
1. `php artisan test --without-tty --do-not-cache-result --filter=ImmersionReadabilityFeatureTest`
2. `php artisan test --without-tty --do-not-cache-result --filter=RelativeTimeComponentTest`
3. `node --test tests/js/post-editor-draft.test.mjs`
4. `composer analyse`
5. 2-3 manuelle Browser-Smokes in produktionsnaher Umgebung

One-Command Tagescheck:
- `scripts/release_phase_a_stability_check.sh --smoke-mode artisan --report-out "docs/PHASE-A-STABILITY-DAY1.md"`
- Optional produktionsnah mit HTTP-Smoke:
  - `scripts/release_phase_a_stability_check.sh --base-url "https://rpg.c76.org" --world-slug "<world-slug>" --smoke-mode http --report-out "docs/PHASE-A-STABILITY-DAY1.md" --smoke-report-out "docs/SMOKE-PHASE-A-DAY1.md"`
- Hinweis: Das Stability-Skript benötigt `node`. Ohne Node auf dem Zielhost den Stability-Check lokal/CI ausführen und serverseitig nur das Phase-A-Smoke-Gate laufen lassen.

### Exit-Kriterien für Phase B
- Keine regressiven Fehler im Posting-Flow in 3-5 aufeinanderfolgenden Tagen
- Keine offenen Upload-/Persistenz-Bugs mit hoher Priorität

## Phase B (Release-Block 2): Welle 3 (Editor)
1. `FEATURE_WAVE3_EDITOR_PREVIEW=true`
2. `FEATURE_WAVE3_DRAFT_AUTOSAVE=true`
3. Smoke:
   - Markdown-Live-Preview rendert und sanitiziert
   - Draft Restore pro Szene+User funktioniert
   - Bearbeiten/Neuer Post loescht Draft nach Submit

## Phase C (Release-Block 3): Welle 4 Start
Empfohlene Reihenfolge:
1. `FEATURE_WAVE4_ACTIVE_CHARACTERS=true`
2. `FEATURE_WAVE4_REACTIONS=true`
3. `FEATURE_WAVE4_MENTIONS=true`

Rollback:
- Einzelnes Feature sofort über Flag deaktivieren
- Kein Schema-Rollback nötig für reine UI/Logik-Deaktivierung
