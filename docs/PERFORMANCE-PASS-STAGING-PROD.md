# Performance-Pass Staging/Prod (MariaDB/MySQL)

Status: abgeschlossen  
Datum: 2026-03-09  
Generated at (report): `2026-03-09T17:53:39+00:00`

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
  - Hauptteil nutzt relevante Indizes
  - Derived-Subquery auf `posts` zeigt `Using temporary; Using filesort`
- `campaign_invitations.inbox_status_specific`
  - nutzt `camp_inv_user_status_created_idx` (`type=ref`, `Using index`)
- `campaign_invitations.by_campaign_status`
  - nutzt `camp_inv_campaign_status_user_idx` (`type=ref`, `Using index`)

## Fazit
- Hotpath-Indexabdeckung auf Prod ist insgesamt gut.
- Kein kritischer Full-Scan in den geprueften Hauptpfaden sichtbar.
- Optimierungspotenzial bleibt bei `scene_subscriptions.unread_count` (derived/temp/filesort).

## Naechste technische Optimierung (optional)
1. `unread_count`-Abfrage auf `EXISTS`-Strategie umstellen (statt `MAX(id) GROUP BY`-Derived-Table).
2. Danach `perf:world-hotpaths` erneut auf Prod ausfuehren und Delta dokumentieren.
