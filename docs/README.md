# Docs-Übersicht

Stand: 2026-06-04

## Source of Truth je Thema
- Einstieg + lokale Kernkommandos: `../README.md`
- Release-, Entwicklungs-, Live-/Build-/Beta-Status: `STATUS.md`
- Planung/Statusachsen: `../ROADMAP.md`
- Release-Flow + Qualitätsgates: `RELEASE-CHECKLISTE.md`
- Betrieb/Incident + Security-Header-Anbindung: `OPERATIONS_RUNBOOK.md`
- Security-Hardening (technisch): `SECURITY.md`
- PWA/Offline-Details: `PWA_OFFLINE.md`
- Architekturstandard (Actions-first + Guardrails): `ARCHITECTURE.md`
- Release-Historie: `../CHANGELOG.md`
- Architekturentscheidungen: `adr/*`

## Kern-Dokumente (pflegepflichtig)
- `PROJEKT-ÜBERSICHT.md`
- `RELEASE-CHECKLISTE.md`
- `STATUS.md`
- `OPERATIONS_RUNBOOK.md`
- `IMMERSION-ARCHITEKTUR.md`
- `ARCHITECTURE.md`
- `DEPLOYMENT.md`
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
4. Bei inhaltlichen Änderungen immer auch `PROJEKT-ÜBERSICHT.md` auf Stand bringen.
5. Bei CI-Workflow-Änderungen (`.github/workflows/ci.yml`) auch `README.md`, `RELEASE-CHECKLISTE.md`, `PROJEKT-ÜBERSICHT.md`, `DEPLOYMENT.md` und die EN-Summaries synchronisieren.
6. `STATUS.md` muss Release, Entwicklungsstand und produktiven Live-Stand getrennt fuehren; produktive Commits nur mit externem Nachweis dokumentieren.
