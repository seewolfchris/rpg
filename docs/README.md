# Docs-Übersicht

Stand: 2026-04-10

## Source of Truth je Thema
- Einstieg + lokale Kernkommandos: `../README.md`
- Planung/Statusachsen: `../ROADMAP.md`
- Release-Flow + Qualitätsgates: `RELEASE-CHECKLISTE.md`
- Betrieb/Incident + Security-Header-Anbindung: `OPERATIONS_RUNBOOK.md`
- Architekturentscheidungen: `adr/*`

## Kern-Dokumente (pflegepflichtig)
- `PROJEKT-ÜBERSICHT.md`
- `RELEASE-CHECKLISTE.md`
- `OPERATIONS_RUNBOOK.md`
- `IMMERSION-ARCHITEKTUR.md`
- `PLESK_DEPLOYMENT_FUER_ANFAENGER.md`
- `GITHUB_PLESK_SETUP.md`
- `adr/*`

## Generierte Reports (regelmäßig aufräumen)
- Perf latest/delta: `PERFORMANCE-POSTS-LATEST-BY-ID-LATEST.md`
- Perf gate latest: `PERFORMANCE-POSTS-LATEST-BY-ID-GATE-LATEST.md`
- Perf staging/prod snapshot: `PERFORMANCE-POSTS-LATEST-BY-ID-STAGING-PROD.md`
- Datierte Perf-Läufe: `PERFORMANCE-POSTS-LATEST-BY-ID-YYYY-MM-DD.md`
- Smoke snapshots: `SMOKE-PASS-*.md`

## Aufräumregel (praktisch)
1. `LATEST` und `GATE-LATEST` immer behalten.
2. Bei datierten Perf-Läufen mindestens behalten:
   - den letzten Lauf
   - die Baseline, auf die `LATEST` aktuell verweist.
3. Ältere datierte Reports entweder löschen oder in einen Archivpfad verschieben.
4. Bei inhaltlichen Änderungen immer auch `PROJEKT-ÜBERSICHT.md` auf Stand bringen.
5. Bei CI-Workflow-Änderungen (`.github/workflows/ci.yml`) auch `README.md`, `PROJEKT-ÜBERSICHT.md` und `PLESK_DEPLOYMENT_FUER_ANFAENGER.md` synchronisieren.
