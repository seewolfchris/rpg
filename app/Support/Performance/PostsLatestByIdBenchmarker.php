<?php

namespace App\Support\Performance;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PostsLatestByIdBenchmarker
{
    /**
     * @return array{
     *   generated_at: string,
     *   connection: string,
     *   driver: string,
     *   world: array{slug: string, id: int|null},
     *   iterations: int,
     *   sample_scene_ids: list<int>,
     *   scenarios: array<string, array{
     *     title: string,
     *     sql: string,
     *     bindings_example: list<int|string>,
     *     explain_rows: list<array<string, mixed>>,
     *     stats: array{
     *       runs: int,
     *       avg_ms: float,
     *       p95_ms: float,
     *       min_ms: float,
     *       max_ms: float
     *     }
     *   }>
     * }
     */
    public function generate(?string $worldSlug = null, int $iterations = 300): array
    {
        $safeIterations = max(20, min(2000, $iterations));
        $connection = DB::connection();
        $connectionName = (string) ($connection->getName() ?? config('database.default', 'default'));
        $driver = $connection->getDriverName();

        $normalizedWorldSlug = $this->resolveWorldSlug($worldSlug);
        $worldId = $this->resolveWorldId($normalizedWorldSlug);
        $sceneIds = $this->resolveBenchmarkSceneIds($worldId);

        $scenarios = [
            'default' => [
                'title' => 'Default planner choice',
                'sql' => 'SELECT id FROM posts WHERE scene_id = ? ORDER BY id DESC LIMIT 20',
            ],
        ];

        if ($this->shouldUseForceIndexForRuntime($driver)) {
            $scenarios['runtime_configured'] = [
                'title' => 'Runtime configured (FORCE INDEX)',
                'sql' => 'SELECT id FROM posts FORCE INDEX ('.$this->configuredForceIndexName().') WHERE scene_id = ? ORDER BY id DESC LIMIT 20',
            ];
        }

        if ($this->supportsForceIndex($driver)) {
            $scenarios['force_index_scene_id_id'] = [
                'title' => 'FORCE INDEX posts_scene_id_id_idx',
                'sql' => 'SELECT id FROM posts FORCE INDEX (posts_scene_id_id_idx) WHERE scene_id = ? ORDER BY id DESC LIMIT 20',
            ];
        }

        $reportScenarios = [];

        foreach ($scenarios as $key => $scenario) {
            $stats = $this->benchmarkScenario($scenario['sql'], $sceneIds, $safeIterations);

            $reportScenarios[$key] = [
                'title' => $scenario['title'],
                'sql' => $scenario['sql'],
                'bindings_example' => [$sceneIds[0]],
                'explain_rows' => $this->explain($driver, $scenario['sql'], [$sceneIds[0]]),
                'stats' => $stats,
            ];
        }

        return [
            'generated_at' => Carbon::now()->toIso8601String(),
            'connection' => $connectionName !== '' ? $connectionName : 'default',
            'driver' => $driver,
            'world' => [
                'slug' => $normalizedWorldSlug,
                'id' => $worldId,
            ],
            'iterations' => $safeIterations,
            'sample_scene_ids' => $sceneIds,
            'scenarios' => $reportScenarios,
        ];
    }

    /**
     * @param  array{
     *   generated_at: string,
     *   connection: string,
     *   driver: string,
     *   world: array{slug: string, id: int|null},
     *   iterations: int,
     *   sample_scene_ids: list<int>,
     *   scenarios: array<string, array{
     *     title: string,
     *     sql: string,
     *     bindings_example: list<int|string>,
     *     explain_rows: list<array<string, mixed>>,
     *     stats: array{
     *       runs: int,
     *       avg_ms: float,
     *       p95_ms: float,
     *       min_ms: float,
     *       max_ms: float
     *     }
     *   }>
     * } $report
     */
    public function toMarkdown(array $report): string
    {
        $lines = [
            '# posts.latest_by_id Benchmark Report',
            '',
            '- Generated at: `'.$report['generated_at'].'`',
            '- Connection: `'.$report['connection'].'`',
            '- Driver: `'.$report['driver'].'`',
            '- World: `'.$report['world']['slug'].'` (id: `'.($report['world']['id'] ?? 'n/a').'`)',
            '- Iterations per scenario: `'.$report['iterations'].'`',
            '- Sample scenes: `'.implode(', ', $report['sample_scene_ids']).'`',
            '',
        ];

        foreach ($report['scenarios'] as $key => $scenario) {
            $lines[] = '## `'.$key.'` - '.$scenario['title'];
            $lines[] = '';
            $lines[] = '- SQL:';
            $lines[] = '```sql';
            $lines[] = $scenario['sql'];
            $lines[] = '```';
            $lines[] = '- Example bindings: `'.json_encode($scenario['bindings_example'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).'`';
            $lines[] = '- Runtime stats:';
            $lines[] = '  - runs: `'.$scenario['stats']['runs'].'`';
            $lines[] = '  - avg: `'.number_format($scenario['stats']['avg_ms'], 3).' ms`';
            $lines[] = '  - p95: `'.number_format($scenario['stats']['p95_ms'], 3).' ms`';
            $lines[] = '  - min: `'.number_format($scenario['stats']['min_ms'], 3).' ms`';
            $lines[] = '  - max: `'.number_format($scenario['stats']['max_ms'], 3).' ms`';
            $lines[] = '- EXPLAIN rows:';

            if ($scenario['explain_rows'] === []) {
                $lines[] = '  - _No rows_';
            } else {
                foreach ($scenario['explain_rows'] as $row) {
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
     * @return list<int>
     */
    private function resolveBenchmarkSceneIds(?int $worldId): array
    {
        $query = DB::table('scenes as s')
            ->leftJoin('posts as p', 'p.scene_id', '=', 's.id')
            ->join('campaigns as c', 'c.id', '=', 's.campaign_id')
            ->select('s.id', DB::raw('COUNT(p.id) as post_count'));

        if ($worldId !== null && Schema::hasColumn('campaigns', 'world_id')) {
            $query->where('c.world_id', $worldId);
        }

        $rows = $query
            ->groupBy('s.id')
            ->havingRaw('COUNT(p.id) > 0')
            ->orderByDesc('post_count')
            ->orderBy('s.id')
            ->limit(5)
            ->get();

        $sceneIds = [];
        foreach ($rows as $row) {
            $sceneId = (int) ($row->id ?? 0);
            if ($sceneId > 0) {
                $sceneIds[] = $sceneId;
            }
        }

        if ($sceneIds !== []) {
            return $sceneIds;
        }

        $fallbackSceneId = DB::table('scenes')->orderBy('id')->value('id');
        $resolvedFallbackSceneId = is_numeric($fallbackSceneId)
            ? max(1, (int) $fallbackSceneId)
            : 1;

        return [$resolvedFallbackSceneId];
    }

    private function supportsForceIndex(string $driver): bool
    {
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return false;
        }

        if (! Schema::hasTable('posts') || ! Schema::hasColumn('posts', 'scene_id')) {
            return false;
        }

        return $this->hasIndex('posts', 'posts_scene_id_id_idx');
    }

    private function shouldUseForceIndexForRuntime(string $driver): bool
    {
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return false;
        }

        if (! (bool) config('performance.posts_latest_by_id.force_index_enabled', false)) {
            return false;
        }

        $indexName = $this->configuredForceIndexName();

        if ($indexName === '') {
            return false;
        }

        if (! Schema::hasTable('posts') || ! Schema::hasColumn('posts', 'scene_id')) {
            return false;
        }

        return $this->hasIndex('posts', $indexName);
    }

    private function configuredForceIndexName(): string
    {
        $indexName = (string) config('performance.posts_latest_by_id.force_index_name', 'posts_scene_id_id_idx');

        return preg_match('/^[A-Za-z0-9_]+$/', $indexName) === 1
            ? $indexName
            : '';
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $rows = DB::select('SHOW INDEX FROM '.$table.' WHERE Key_name = ?', [$indexName]);

        return $rows !== [];
    }

    /**
     * @param  list<int>  $sceneIds
     * @return array{
     *   runs: int,
     *   avg_ms: float,
     *   p95_ms: float,
     *   min_ms: float,
     *   max_ms: float
     * }
     */
    private function benchmarkScenario(string $sql, array $sceneIds, int $iterations): array
    {
        $latenciesMs = [];
        $sceneCount = count($sceneIds);

        $warmupRuns = min(20, intdiv($iterations, 5));
        for ($i = 0; $i < $warmupRuns; $i++) {
            DB::select($sql, [$sceneIds[$i % $sceneCount]]);
        }

        for ($i = 0; $i < $iterations; $i++) {
            $sceneId = $sceneIds[$i % $sceneCount];
            $startNs = hrtime(true);
            DB::select($sql, [$sceneId]);
            $elapsedNs = hrtime(true) - $startNs;
            $latenciesMs[] = $elapsedNs / 1_000_000;
        }

        sort($latenciesMs);
        $runs = count($latenciesMs);
        $p95Index = max(0, (int) ceil($runs * 0.95) - 1);

        return [
            'runs' => $runs,
            'avg_ms' => $runs > 0 ? array_sum($latenciesMs) / $runs : 0.0,
            'p95_ms' => $runs > 0 ? $latenciesMs[$p95Index] : 0.0,
            'min_ms' => $runs > 0 ? $latenciesMs[0] : 0.0,
            'max_ms' => $runs > 0 ? $latenciesMs[$runs - 1] : 0.0,
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
}
