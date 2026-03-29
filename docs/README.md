# Docs-Uebersicht

Stand: 2026-03-29

## Kern-Dokumente (pflegepflichtig)
- `PROJEKT-ÜBERSICHT.md`
- `RELEASE-CHECKLISTE.md`
- `OPERATIONS_RUNBOOK.md`
- `IMMERSION-ARCHITEKTUR.md`
- `PLESK_DEPLOYMENT_FUER_ANFAENGER.md`
- `GITHUB_PLESK_SETUP.md`
- `adr/*`

## Generierte Reports (regelmaessig aufraeumen)
- Perf latest/delta: `PERFORMANCE-POSTS-LATEST-BY-ID-LATEST.md`
- Perf gate latest: `PERFORMANCE-POSTS-LATEST-BY-ID-GATE-LATEST.md`
- Perf staging/prod snapshot: `PERFORMANCE-POSTS-LATEST-BY-ID-STAGING-PROD.md`
- Datierte Perf-Laeufe: `PERFORMANCE-POSTS-LATEST-BY-ID-YYYY-MM-DD.md`
- Smoke snapshots: `SMOKE-PASS-*.md`

## Aufraeumregel (praktisch)
1. `LATEST` und `GATE-LATEST` immer behalten.
2. Bei datierten Perf-Laeufen mindestens behalten:
   - den letzten Lauf
   - die Baseline, auf die `LATEST` aktuell verweist.
3. Aeltere datierte Reports entweder loeschen oder in einen Archivpfad verschieben.
4. Bei inhaltlichen Aenderungen immer auch `PROJEKT-ÜBERSICHT.md` auf Stand bringen.
