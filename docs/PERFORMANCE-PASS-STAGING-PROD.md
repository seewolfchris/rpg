# Performance-Pass Staging/Prod (MariaDB/MySQL)

Status: abgeschlossen  
Datum: 2026-03-09  
Generated at (report): `2026-03-09T18:20:30+00:00`

## Laufkontext
- Umgebung: `rpg.c76.org` (Prod)
- Connection/Driver: `mysql`
- Welt: `chroniken-der-asche` (`world_id=1`)
- Samples: `scene_id=1`, `campaign_id=1`, `user_id=1`

## Ausgefuehrter Command

```bash
cd /var/www/vhosts/c76.org/rpg.c76.org
PHP_BIN=/opt/plesk/php/8.5/bin/php
$PHP_BIN artisan perf:world-hotpaths --world=chroniken-der-asche --out=docs/PERFORMANCE-PASS-STAGING-PROD.md
```

## Ergebnis (Kurzbewertung)
- `posts.thread_by_created_at`
  - nutzt `posts_scene_id_created_at_index` (`type=ref`)
- `posts.latest_by_id`
  - nutzt `PRIMARY` (`type=index`, `Using where`)
  - `posts_scene_id_id_idx` ist vorhanden, wurde im Sample-Plan aber nicht gewaehlt
- `scene_subscriptions.dashboard`
  - nutzt `scene_sub_user_updated_idx` (`type=ref`, `Using index`)
- `scene_subscriptions.unread_count`
  - laeuft jetzt mit `EXISTS`-Strategie
  - `posts`-Teil nutzt Indexzugriff (`FirstMatch(c)`)
  - kein `Using temporary; Using filesort` im Plan sichtbar
- `campaign_invitations.inbox_status_specific`
  - nutzt `camp_inv_user_status_created_idx` (`type=ref`, `Using index`)
- `campaign_invitations.by_campaign_status`
  - nutzt `camp_inv_campaign_status_user_idx` (`type=ref`, `Using index`)

## Delta zum vorherigen Prod-Lauf
- Vorher (`17:53:39`): `scene_subscriptions.unread_count` mit Derived-Subquery (`MAX(id) GROUP BY`) und `Using temporary; Using filesort`.
- Nachher (`18:20:30`): `scene_subscriptions.unread_count` mit `EXISTS`-Plan und Indexzugriff auf `posts`, ohne `temporary/filesort`.

## Fazit
- Hotpath-Indexabdeckung auf Prod ist insgesamt gut.
- Kein kritischer Full-Scan in den geprueften Hauptpfaden sichtbar.
- Die zuvor offene `unread_count`-Optimierung ist erfolgreich abgeschlossen.

## Naechste technische Optimierung (optional)
1. `posts.latest_by_id` bei steigender Datenmenge erneut beobachten (MySQL waehlt aktuell `PRIMARY` statt `posts_scene_id_id_idx`).
2. Bei Lastanstieg optional Query-Varianten fuer `latest_by_id` benchmarken und dokumentieren.

## Zusatzlauf: `posts.latest_by_id` Benchmark (Prod, 2026-03-09)
- Separater Benchmark-Report:
  - `docs/PERFORMANCE-POSTS-LATEST-BY-ID-STAGING-PROD.md`
- Kurzfazit:
  - `default`: avg `0.179 ms`, p95 `0.241 ms`
  - `FORCE INDEX posts_scene_id_id_idx`: avg `0.157 ms`, p95 `0.195 ms`
  - `FORCE INDEX` ist im Sample messbar schneller, aber beide Varianten sind bereits sehr schnell.
