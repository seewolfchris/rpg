# posts.latest_by_id Release Perf Gate

- Evaluated at: `2026-03-12T10:19:56Z`
- Benchmark latest: `docs/PERFORMANCE-POSTS-LATEST-BY-ID-LATEST.md`
- Benchmark generated at: `2026-03-12T10:19:55+00:00`
- Source report: `docs/PERFORMANCE-POSTS-LATEST-BY-ID-2026-03-12.md`
- Scenario: `default`
- Thresholds:
  - warn avg > `10%`
  - warn p95 > `15%`
  - fail avg > `25%`
  - fail p95 > `35%`

## Result
- Status: `GRUEN`
- avg delta vs baseline: `-25.23%`
- p95 delta vs baseline: `-41.54%`
- Reason: Keine relevante Regression gegen Baseline.

## Interpretation
- `GRUEN`: Release kann ohne Performance-Sondermassnahmen weiterlaufen.
- `GELB`: Release moeglich, aber Delta beobachten und bei Bedarf erneut messen.
- `ROT`: Release-Blocker fuer diesen Hotpath, erst Ursache klaeren.
