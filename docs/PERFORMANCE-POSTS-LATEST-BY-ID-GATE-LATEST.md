# posts.latest_by_id Release Perf Gate

- Evaluated at: `2026-03-20T02:23:14Z`
- Benchmark latest: `docs/PERFORMANCE-POSTS-LATEST-BY-ID-LATEST.md`
- Benchmark generated at: `2026-03-20T02:22:58+00:00`
- Source report: `docs/PERFORMANCE-POSTS-LATEST-BY-ID-2026-03-20.md`
- Scenario: `default`
- Thresholds:
  - warn avg > `10%`
  - warn p95 > `15%`
  - fail avg > `0%`
  - fail p95 > `0%`

## Result
- Exit-Code: `0`
- Status: `ROT`
- avg delta vs baseline: `+0.41%`
- p95 delta vs baseline: `+17.57%`
- Median latency: `0.244 ms`
- P99 latency: `0.348 ms`
- Reason: Regression über Fail-Schwelle.

## Hint-Entscheidung
- Hint-Entscheidung: `FORCE_INDEX=0`
- Begründung: last_3_not_all_green
- Letzte 3 Gates: `insufficient_history`
- WARN: Median/P99 nicht verfügbar -> Fallback avg->Median, p95->P99
- Median-Proxy (avg): n/a ms (n/a% vom Vor) → Schwellen angepasst: <=90%
- P99-Proxy (p95): n/a ms (n/a% vom Vor) → Schwellen angepasst: <=105%

## Interpretation
- `GRUEN`: Release kann ohne Performance-Sondermaßnahmen weiterlaufen.
- `GELB`: Release möglich, aber Delta beobachten und bei Bedarf erneut messen.
- `ROT`: Report-only Signal; Skript endet nur bei technischen Fehlern mit non-zero.
