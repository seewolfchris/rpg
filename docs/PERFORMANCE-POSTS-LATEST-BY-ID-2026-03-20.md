# posts.latest_by_id Benchmark Report

- Generated at: `2026-03-20T02:22:58+00:00`
- Connection: `sqlite`
- Driver: `sqlite`
- World: `chroniken-der-asche` (id: `1`)
- Iterations per scenario: `50`
- Sample scenes: `1`

## `default` - Default planner choice

- SQL:
```sql
SELECT id FROM posts WHERE scene_id = ? ORDER BY id DESC LIMIT 20
```
- Example bindings: `[1]`
- Runtime stats:
  - runs: `50`
  - avg: `0.244 ms`
  - p95: `0.348 ms`
  - min: `0.227 ms`
  - max: `0.436 ms`
- EXPLAIN rows:
  - `{"id":4,"parent":0,"notused":53,"detail":"SEARCH posts USING COVERING INDEX posts_scene_id_id_idx (scene_id=?)"}`

