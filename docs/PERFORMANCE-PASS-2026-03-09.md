# Performance-Pass 2026-03-09 (Weltkontext-Hotpaths)

## Scope
- Fokus-Tabellen: `posts`, `scene_subscriptions`, `campaign_invitations`
- Ziel: Index-Nutzung fuer Kernabfragen sichtbar validieren (`EXPLAIN QUERY PLAN`)
- Umgebung: lokale SQLite-DB (`database/database.sqlite`) nach allen aktuellen Migrationen

## Umgesetzte Optimierungen
1. Neue Indizes (Migration `2026_03_09_160000_add_dashboard_and_invitation_hotpath_indexes.php`)
   - `scene_subscriptions (user_id, updated_at)` als `scene_sub_user_updated_idx`
   - `campaign_invitations (user_id, status, created_at)` als `camp_inv_user_status_created_idx`
2. Query-Optimierung in `CampaignInvitationController@index`
   - Sortierung per `CASE status` nur noch im `status=all`-Pfad
   - Bei konkretem Status nur `latest(created_at)` (indexfreundlicher)

## EXPLAIN-Ergebnis (Kurzfassung)
- `posts` Thread nach `scene_id + created_at`:
  - nutzt `posts_scene_id_created_at_index`
- `posts` Latest-IDs nach `scene_id + id`:
  - nutzt `posts_scene_id_id_idx`
- `scene_subscriptions` Dashboard (`user_id`, sortiert nach `updated_at`):
  - nutzt `scene_sub_user_updated_idx`
  - kein temporärer Sort-Baum mehr
- `campaign_invitations` Inbox mit Statusfilter:
  - nutzt `camp_inv_user_status_created_idx (user_id, status)`
- `campaign_invitations` pro Kampagne/Status:
  - nutzt `camp_inv_campaign_status_user_idx`
- `campaign_invitations` mit `status=all` + `CASE`-Sortierung:
  - weiterhin Temp-Sort erwartbar (fachlogisch bedingt durch Priorisierung)

## Bewertung
- Hotpaths sind indexseitig fuer die wichtigsten Standardabfragen abgedeckt.
- Kein offensichtlicher Full-Scan auf den geprueften Kernabfragen.
- Restthema: `status=all`-Inbox-Sortierung bleibt bewusst sortierintensiv.

## Offene Nacharbeit (Staging/Prod)
- Staging/Prod-Lauf ueber neuen Artisan-Command ausfuehren und Report speichern:
  - `php artisan perf:world-hotpaths --world=chroniken-der-asche --out=docs/PERFORMANCE-PASS-STAGING-PROD.md`
- Bei Bedarf separates Materialized-/Denormalized-Sortfeld fuer `status=all` pruefen.

## Umsetzungshilfe fuer Plesk (MariaDB/MySQL)
Auf dem Server im Projektverzeichnis:

```bash
cd /var/www/vhosts/c76.org/rpg.c76.org
PHP_BIN=/opt/plesk/php/8.5/bin/php
$PHP_BIN artisan perf:world-hotpaths --world=chroniken-der-asche --out=docs/PERFORMANCE-PASS-STAGING-PROD.md
```

Danach Report pruefen:

```bash
cat docs/PERFORMANCE-PASS-STAGING-PROD.md
```
