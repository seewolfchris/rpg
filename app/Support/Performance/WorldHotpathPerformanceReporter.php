<?php

namespace App\Support\Performance;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WorldHotpathPerformanceReporter
{
    /**
     * @return array{
     *   generated_at: string,
     *   connection: string,
     *   driver: string,
     *   world: array{slug: string, id: int|null},
     *   samples: array{scene_id: int, campaign_id: int, user_id: int},
     *   indexes: array<string, list<array{name: string, unique: bool, columns: list<string>}>>,
     *   queries: array<string, array{title: string, sql: string, bindings: list<int|string>, rows: list<array<string, mixed>>}>
     * }
     */
    public function generate(?string $worldSlug = null): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $normalizedWorldSlug = $this->resolveWorldSlug($worldSlug);
        $worldId = $this->resolveWorldId($normalizedWorldSlug);
        $samples = $this->resolveSampleIds($worldId);

        $queries = $this->queryDefinitions($worldId, $samples);
        $explainedQueries = [];

        foreach ($queries as $key => $definition) {
            $explainedQueries[$key] = [
                'title' => $definition['title'],
                'sql' => $definition['sql'],
                'bindings' => $definition['bindings'],
                'rows' => $this->explain($driver, $definition['sql'], $definition['bindings']),
            ];
        }

        return [
            'generated_at' => Carbon::now()->toIso8601String(),
            'connection' => $connection->getName(),
            'driver' => $driver,
            'world' => [
                'slug' => $normalizedWorldSlug,
                'id' => $worldId,
            ],
            'samples' => $samples,
            'indexes' => $this->collectIndexes($driver),
            'queries' => $explainedQueries,
        ];
    }

    /**
     * @param  array{
     *   generated_at: string,
     *   connection: string,
     *   driver: string,
     *   world: array{slug: string, id: int|null},
     *   samples: array{scene_id: int, campaign_id: int, user_id: int},
     *   indexes: array<string, list<array{name: string, unique: bool, columns: list<string>}>>,
     *   queries: array<string, array{title: string, sql: string, bindings: list<int|string>, rows: list<array<string, mixed>>}>
     * } $report
     */
    public function toMarkdown(array $report): string
    {
        $lines = [
            '# World-Hotpath-Performance Report',
            '',
            '- Generated at: `'.$report['generated_at'].'`',
            '- Connection: `'.$report['connection'].'`',
            '- Driver: `'.$report['driver'].'`',
            '- World: `'.$report['world']['slug'].'` (id: `'.($report['world']['id'] ?? 'n/a').'`)',
            '- Samples: `scene_id='.$report['samples']['scene_id'].'`, `campaign_id='.$report['samples']['campaign_id'].'`, `user_id='.$report['samples']['user_id'].'`',
            '',
            '## Indexes',
            '',
        ];

        foreach ($report['indexes'] as $table => $indexes) {
            $lines[] = '### `'.$table.'`';
            if ($indexes === []) {
                $lines[] = '- _No indexes found_';
                $lines[] = '';

                continue;
            }

            foreach ($indexes as $index) {
                $lines[] = '- `'.$index['name'].'` (unique: '.($index['unique'] ? 'yes' : 'no').')';
                $lines[] = '  - columns: `'.implode('`, `', $index['columns']).'`';
            }
            $lines[] = '';
        }

        $lines[] = '## EXPLAIN';
        $lines[] = '';

        foreach ($report['queries'] as $key => $query) {
            $lines[] = '### `'.$key.'` - '.$query['title'];
            $lines[] = '';
            $lines[] = '- SQL:';
            $lines[] = '```sql';
            $lines[] = $query['sql'];
            $lines[] = '```';
            $lines[] = '- Bindings: `'.json_encode($query['bindings'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).'`';
            $lines[] = '- Plan rows:';

            if ($query['rows'] === []) {
                $lines[] = '  - _No rows_';
            } else {
                foreach ($query['rows'] as $row) {
                    $lines[] = '  - `'.json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).'`';
                }
            }
            $lines[] = '';
        }

        return implode("\n", $lines)."\n";
    }

    private function resolveWorldSlug(?string $worldSlug): string
    {
        $resolved = is_string($worldSlug) ? trim($worldSlug) : '';

        if ($resolved !== '') {
            return $resolved;
        }

        return (string) config('worlds.default_slug', 'chroniken-der-asche');
    }

    private function resolveWorldId(string $worldSlug): ?int
    {
        if (! Schema::hasTable('worlds')) {
            return null;
        }

        $worldId = DB::table('worlds')
            ->where('slug', $worldSlug)
            ->value('id');

        return is_numeric($worldId) ? (int) $worldId : null;
    }

    /**
     * @return array{scene_id: int, campaign_id: int, user_id: int}
     */
    private function resolveSampleIds(?int $worldId): array
    {
        $campaignId = $this->resolveCampaignId($worldId);

        $sceneIdValue = DB::table('scenes')
            ->when($campaignId > 0, fn ($query) => $query->where('campaign_id', $campaignId))
            ->orderBy('id')
            ->value('id');
        $sceneId = is_numeric($sceneIdValue) ? (int) $sceneIdValue : 1;

        $userId = $this->resolveUserId($worldId, $campaignId, $sceneId);

        return [
            'scene_id' => $sceneId,
            'campaign_id' => $campaignId,
            'user_id' => $userId,
        ];
    }

    private function resolveCampaignId(?int $worldId): int
    {
        $campaignIdValue = DB::table('campaigns')
            ->when(
                $worldId !== null && Schema::hasColumn('campaigns', 'world_id'),
                fn ($query) => $query->where('world_id', $worldId)
            )
            ->orderBy('id')
            ->value('id');

        return is_numeric($campaignIdValue) ? (int) $campaignIdValue : 1;
    }

    private function resolveUserId(?int $worldId, int $campaignId, int $sceneId): int
    {
        $subscriptionUserId = DB::table('scene_subscriptions')
            ->when($sceneId > 0, fn ($query) => $query->where('scene_id', $sceneId))
            ->orderBy('id')
            ->value('user_id');
        if (is_numeric($subscriptionUserId)) {
            return (int) $subscriptionUserId;
        }

        $invitationUserId = DB::table('campaign_invitations')
            ->when($campaignId > 0, fn ($query) => $query->where('campaign_id', $campaignId))
            ->orderBy('id')
            ->value('user_id');
        if (is_numeric($invitationUserId)) {
            return (int) $invitationUserId;
        }

        if ($worldId !== null && Schema::hasColumn('characters', 'world_id')) {
            $characterUserId = DB::table('characters')
                ->where('world_id', $worldId)
                ->orderBy('id')
                ->value('user_id');
            if (is_numeric($characterUserId)) {
                return (int) $characterUserId;
            }
        }

        $userId = DB::table('users')
            ->orderBy('id')
            ->value('id');

        return is_numeric($userId) ? (int) $userId : 1;
    }

    /**
     * @param  array{scene_id: int, campaign_id: int, user_id: int}  $samples
     * @return array<string, array{title: string, sql: string, bindings: list<int|string>}>
     */
    private function queryDefinitions(?int $worldId, array $samples): array
    {
        $worldFilter = '';
        $worldBindings = [];

        if ($worldId !== null && Schema::hasColumn('campaigns', 'world_id')) {
            $worldFilter = ' AND c.world_id = ?';
            $worldBindings[] = $worldId;
        }

        return [
            'posts.thread_by_created_at' => [
                'title' => 'Scene thread ordered by created_at',
                'sql' => 'SELECT id FROM posts WHERE scene_id = ? ORDER BY created_at DESC LIMIT 20',
                'bindings' => [$samples['scene_id']],
            ],
            'posts.latest_by_id' => [
                'title' => 'Scene newest posts by id',
                'sql' => 'SELECT id FROM posts WHERE scene_id = ? ORDER BY id DESC LIMIT 20',
                'bindings' => [$samples['scene_id']],
            ],
            'scene_subscriptions.dashboard' => [
                'title' => 'Subscription dashboard list by updated_at',
                'sql' => 'SELECT id FROM scene_subscriptions WHERE user_id = ? ORDER BY updated_at DESC LIMIT 20',
                'bindings' => [$samples['user_id']],
            ],
            'scene_subscriptions.unread_count' => [
                'title' => 'Unread counter with EXISTS strategy',
                'sql' => 'SELECT COUNT(*) '
                    .'FROM scene_subscriptions ss '
                    .'JOIN scenes s ON s.id = ss.scene_id '
                    .'JOIN campaigns c ON c.id = s.campaign_id '
                    .'WHERE ss.user_id = ?'.$worldFilter.' '
                    .'AND EXISTS ('
                    .'SELECT 1 FROM posts p '
                    .'WHERE p.scene_id = ss.scene_id '
                    .'AND p.id > COALESCE(ss.last_read_post_id, 0)'
                    .')',
                'bindings' => array_values(array_merge([$samples['user_id']], $worldBindings)),
            ],
            'campaign_invitations.inbox_status_specific' => [
                'title' => 'Invitation inbox (status filtered)',
                'sql' => 'SELECT id FROM campaign_invitations WHERE user_id = ? AND status = ? ORDER BY created_at DESC LIMIT 20',
                'bindings' => [$samples['user_id'], 'pending'],
            ],
            'campaign_invitations.by_campaign_status' => [
                'title' => 'Invitations by campaign + status',
                'sql' => 'SELECT id FROM campaign_invitations WHERE campaign_id = ? AND status = ? LIMIT 20',
                'bindings' => [$samples['campaign_id'], 'accepted'],
            ],
        ];
    }

    /**
     * @param  list<int|string>  $bindings
     * @return list<array<string, mixed>>
     */
    private function explain(string $driver, string $sql, array $bindings): array
    {
        $prefix = $driver === 'sqlite' ? 'EXPLAIN QUERY PLAN ' : 'EXPLAIN ';

        $rows = DB::select($prefix.$sql, $bindings);

        /** @var list<array<string, mixed>> $normalizedRows */
        $normalizedRows = array_map(static fn ($row): array => (array) $row, $rows);

        return $normalizedRows;
    }

    /**
     * @return array<string, list<array{name: string, unique: bool, columns: list<string>}>>
     */
    private function collectIndexes(string $driver): array
    {
        $result = [];

        foreach (['posts', 'scene_subscriptions', 'campaign_invitations'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $result[$table] = $driver === 'sqlite'
                ? $this->collectSqliteIndexes($table)
                : $this->collectMySqlIndexes($table);
        }

        return $result;
    }

    /**
     * @return list<array{name: string, unique: bool, columns: list<string>}>
     */
    private function collectSqliteIndexes(string $table): array
    {
        $indexRows = DB::select("PRAGMA index_list('".$table."')");
        $result = [];

        foreach ($indexRows as $indexRow) {
            $indexName = (string) ($indexRow->name ?? '');
            if ($indexName === '') {
                continue;
            }

            $columnRows = DB::select("PRAGMA index_info('".$indexName."')");
            $columns = [];
            foreach ($columnRows as $columnRow) {
                $columnName = (string) ($columnRow->name ?? '');
                if ($columnName !== '') {
                    $columns[] = $columnName;
                }
            }

            $result[] = [
                'name' => $indexName,
                'unique' => (int) ($indexRow->unique ?? 0) === 1,
                'columns' => $columns,
            ];
        }

        return $result;
    }

    /**
     * @return list<array{name: string, unique: bool, columns: list<string>}>
     */
    private function collectMySqlIndexes(string $table): array
    {
        $indexRows = DB::select('SHOW INDEX FROM `'.$table.'`');
        $grouped = [];

        foreach ($indexRows as $indexRow) {
            $indexName = (string) ($indexRow->Key_name ?? '');
            if ($indexName === '') {
                continue;
            }

            if (! array_key_exists($indexName, $grouped)) {
                $grouped[$indexName] = [
                    'name' => $indexName,
                    'unique' => (int) ($indexRow->Non_unique ?? 1) === 0,
                    'columns' => [],
                ];
            }

            $sequence = (int) ($indexRow->Seq_in_index ?? 0);
            $grouped[$indexName]['columns'][$sequence] = (string) ($indexRow->Column_name ?? '');
        }

        $result = [];
        foreach ($grouped as $index) {
            ksort($index['columns']);
            $index['columns'] = array_values(array_filter(
                $index['columns'],
                static fn (string $column): bool => $column !== ''
            ));
            $result[] = $index;
        }

        return $result;
    }
}
