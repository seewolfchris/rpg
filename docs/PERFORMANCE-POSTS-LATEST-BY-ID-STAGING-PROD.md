# posts.latest_by_id Benchmark Report (Staging/Prod)

Status: abgeschlossen  
Datum: 2026-03-09  
Generated at (report): `2026-03-09T22:05:27+00:00`

## Laufkontext
- Umgebung: `rpg.c76.org` (Prod)
- Connection/Driver: `mysql`
- Welt: `chroniken-der-asche` (`world_id=1`)
- Iterationen je Szenario: `400`
- Sample-Scene: `scene_id=1`

## Ausgefuehrter Command

```bash
cd /var/www/vhosts/c76.org/rpg.c76.org
/opt/plesk/php/8.5/bin/php artisan perf:posts-latest-by-id-benchmark --world=chroniken-der-asche --iterations=400 --out=docs/PERFORMANCE-POSTS-LATEST-BY-ID-STAGING-PROD.md
```

## Ergebnis

### `default` - Default planner choice
- SQL:
```sql
SELECT id FROM posts WHERE scene_id = ? ORDER BY id DESC LIMIT 20
```
- Runtime stats:
  - runs: `400`
  - avg: `0.179 ms`
  - p95: `0.241 ms`
  - min: `0.136 ms`
  - max: `0.509 ms`
- EXPLAIN:
  - `key=PRIMARY`, `type=index`, `Extra=Using where`

### `force_index_scene_id_id` - FORCE INDEX posts_scene_id_id_idx
- SQL:
```sql
SELECT id FROM posts FORCE INDEX (posts_scene_id_id_idx) WHERE scene_id = ? ORDER BY id DESC LIMIT 20
```
- Runtime stats:
  - runs: `400`
  - avg: `0.157 ms`
  - p95: `0.195 ms`
  - min: `0.135 ms`
  - max: `0.248 ms`
- EXPLAIN:
  - `key=posts_scene_id_id_idx`, `type=ref`, `Extra=Using where; Using index`

## Bewertung
- `default` ist bereits sehr schnell im Sub-Millisekundenbereich.
- `FORCE INDEX` ist im Sample messbar schneller:
  - avg: ca. `12%` besser (`0.179` -> `0.157` ms)
  - p95: ca. `19%` besser (`0.241` -> `0.195` ms)
- Kein akuter Handlungsdruck, aber klarer Hinweis, dass `posts_scene_id_id_idx` fuer diesen Hotpath effizienter ist.

## Empfehlung
1. Zunaechst ohne Query-Hint produktiv lassen (Stabilitaet).
2. Bei wachsender Datenmenge oder Lastspitzen:
   - A/B-Messung mit realem Traffic wiederholen.
   - Optional MySQL-spezifischen Query-Hint fuer diesen einen Hotpath pruefen.
3. Benchmark bei groesseren Datenupdates erneut laufen lassen und Delta dokumentieren.

