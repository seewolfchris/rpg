# Performance-Pass Staging/Prod (MariaDB/MySQL)

Status: ausstehend (Serverlauf notwendig)

## Ziel
- EXPLAIN-Validierung der Welt-Hotpaths mit echten Daten auf Staging/Prod.
- Fokus: `posts`, `scene_subscriptions`, `campaign_invitations`.

## Ausfuehrung auf Plesk

```bash
cd /var/www/vhosts/c76.org/rpg.c76.org
PHP_BIN=/opt/plesk/php/8.5/bin/php
$PHP_BIN artisan perf:world-hotpaths --world=chroniken-der-asche --out=docs/PERFORMANCE-PASS-STAGING-PROD.md
cat docs/PERFORMANCE-PASS-STAGING-PROD.md
```

## Soll-Erwartung
- `posts.thread_by_created_at`: Index auf `(scene_id, created_at)` wird genutzt.
- `posts.latest_by_id`: Index auf `(scene_id, id)` wird genutzt.
- `scene_subscriptions.dashboard`: Index auf `(user_id, updated_at)` wird genutzt.
- `campaign_invitations.inbox_status_specific`: Index auf `(user_id, status, created_at)` wird genutzt.
- `campaign_invitations.by_campaign_status`: Index auf `(campaign_id, status, user_id)` wird genutzt.

## Ergebnisprotokoll (eintragen)
- Datum/Zeit:
- Umgebung:
- MariaDB/MySQL Version:
- Report-Datei:
- Auffaelligkeiten:
- Naechste Optimierungen:
