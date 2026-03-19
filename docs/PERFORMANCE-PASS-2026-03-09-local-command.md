# World-Hotpath-Performance Report

- Generated at: `2026-03-09T16:28:56+00:00`
- Connection: `sqlite`
- Driver: `sqlite`
- World: `chroniken-der-asche` (id: `1`)
- Samples: `scene_id=1`, `campaign_id=1`, `user_id=1`

## Indexes

### `posts`
- `posts_scene_id_id_idx` (unique: no)
  - columns: `scene_id`, `id`
- `posts_scene_id_is_pinned_pinned_at_index` (unique: no)
  - columns: `scene_id`, `is_pinned`, `pinned_at`
- `posts_user_id_created_at_index` (unique: no)
  - columns: `user_id`, `created_at`
- `posts_scene_id_created_at_index` (unique: no)
  - columns: `scene_id`, `created_at`
- `posts_post_type_index` (unique: no)
  - columns: `post_type`
- `posts_moderation_status_created_at_index` (unique: no)
  - columns: `moderation_status`, `created_at`

### `scene_subscriptions`
- `scene_sub_user_updated_idx` (unique: no)
  - columns: `user_id`, `updated_at`
- `scene_sub_scene_read_user_idx` (unique: no)
  - columns: `scene_id`, `last_read_post_id`, `user_id`
- `scene_sub_user_scene_idx` (unique: no)
  - columns: `user_id`, `scene_id`
- `scene_subscriptions_scene_id_last_read_post_id_index` (unique: no)
  - columns: `scene_id`, `last_read_post_id`
- `scene_subscriptions_user_id_last_read_post_id_index` (unique: no)
  - columns: `user_id`, `last_read_post_id`
- `scene_subscriptions_user_id_is_muted_index` (unique: no)
  - columns: `user_id`, `is_muted`
- `scene_subscriptions_scene_id_user_id_unique` (unique: yes)
  - columns: `scene_id`, `user_id`
- `scene_subscriptions_scene_id_is_muted_index` (unique: no)
  - columns: `scene_id`, `is_muted`

### `campaign_invitations`
- `camp_inv_user_status_created_idx` (unique: no)
  - columns: `user_id`, `status`, `created_at`
- `camp_inv_user_status_role_idx` (unique: no)
  - columns: `user_id`, `status`, `role`
- `camp_inv_campaign_status_user_idx` (unique: no)
  - columns: `campaign_id`, `status`, `user_id`
- `campaign_invitations_role_created_at_index` (unique: no)
  - columns: `role`, `created_at`
- `campaign_invitations_status_created_at_index` (unique: no)
  - columns: `status`, `created_at`
- `campaign_invitations_campaign_id_created_at_index` (unique: no)
  - columns: `campaign_id`, `created_at`
- `campaign_invitations_user_id_created_at_index` (unique: no)
  - columns: `user_id`, `created_at`
- `campaign_invitations_campaign_id_user_id_unique` (unique: yes)
  - columns: `campaign_id`, `user_id`

## EXPLAIN

### `posts.thread_by_created_at` - Scene thread ordered by created_at

- SQL:
```sql
SELECT id FROM posts WHERE scene_id = ? ORDER BY created_at DESC LIMIT 20
```
- Bindings: `[1]`
- Plan rows:
  - `{"id":4,"parent":0,"notused":53,"detail":"SEARCH posts USING COVERING INDEX posts_scene_id_created_at_index (scene_id=?)"}`

### `posts.latest_by_id` - Scene newest posts by id

- SQL:
```sql
SELECT id FROM posts WHERE scene_id = ? ORDER BY id DESC LIMIT 20
```
- Bindings: `[1]`
- Plan rows:
  - `{"id":4,"parent":0,"notused":53,"detail":"SEARCH posts USING COVERING INDEX posts_scene_id_id_idx (scene_id=?)"}`

### `scene_subscriptions.dashboard` - Subscription dashboard list by updated_at

- SQL:
```sql
SELECT id FROM scene_subscriptions WHERE user_id = ? ORDER BY updated_at DESC LIMIT 20
```
- Bindings: `[1]`
- Plan rows:
  - `{"id":4,"parent":0,"notused":54,"detail":"SEARCH scene_subscriptions USING COVERING INDEX scene_sub_user_updated_idx (user_id=?)"}`

### `scene_subscriptions.unread_count` - Unread counter with latest post join

- SQL:
```sql
SELECT COUNT(*) FROM scene_subscriptions ss JOIN scenes s ON s.id = ss.scene_id JOIN campaigns c ON c.id = s.campaign_id LEFT JOIN (SELECT scene_id, MAX(id) AS latest_post_id FROM posts GROUP BY scene_id) lp ON lp.scene_id = ss.scene_id WHERE ss.user_id = ? AND c.world_id = ? AND lp.latest_post_id IS NOT NULL AND (ss.last_read_post_id IS NULL OR ss.last_read_post_id < lp.latest_post_id)
```
- Bindings: `[1,1]`
- Plan rows:
  - `{"id":3,"parent":0,"notused":0,"detail":"MATERIALIZE lp"}`
  - `{"id":10,"parent":3,"notused":209,"detail":"SCAN posts USING COVERING INDEX posts_scene_id_id_idx"}`
  - `{"id":49,"parent":0,"notused":62,"detail":"SEARCH ss USING INDEX scene_sub_user_updated_idx (user_id=?)"}`
  - `{"id":56,"parent":0,"notused":45,"detail":"SEARCH s USING INTEGER PRIMARY KEY (rowid=?)"}`
  - `{"id":59,"parent":0,"notused":45,"detail":"SEARCH c USING INTEGER PRIMARY KEY (rowid=?)"}`
  - `{"id":68,"parent":0,"notused":0,"detail":"BLOOM FILTER ON lp (scene_id=?)"}`
  - `{"id":78,"parent":0,"notused":47,"detail":"SEARCH lp USING AUTOMATIC COVERING INDEX (scene_id=?) LEFT-JOIN"}`

### `campaign_invitations.inbox_status_specific` - Invitation inbox (status filtered)

- SQL:
```sql
SELECT id FROM campaign_invitations WHERE user_id = ? AND status = ? ORDER BY created_at DESC LIMIT 20
```
- Bindings: `[1,"pending"]`
- Plan rows:
  - `{"id":4,"parent":0,"notused":54,"detail":"SEARCH campaign_invitations USING COVERING INDEX camp_inv_user_status_created_idx (user_id=? AND status=?)"}`

### `campaign_invitations.by_campaign_status` - Invitations by campaign + status

- SQL:
```sql
SELECT id FROM campaign_invitations WHERE campaign_id = ? AND status = ? LIMIT 20
```
- Bindings: `[1,"accepted"]`
- Plan rows:
  - `{"id":3,"parent":0,"notused":54,"detail":"SEARCH campaign_invitations USING COVERING INDEX camp_inv_campaign_status_user_idx (campaign_id=? AND status=?)"}`

