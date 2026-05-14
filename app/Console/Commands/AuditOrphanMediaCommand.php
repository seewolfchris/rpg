<?php

namespace App\Console\Commands;

use App\Models\Handout;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class AuditOrphanMediaCommand extends Command
{
    protected $signature = 'rpg:audit-orphan-media {--json : Output the audit result as JSON}';

    protected $description = 'Audit orphaned media rows for posts and handouts (read-only).';

    public function handle(): int
    {
        $missingTables = $this->missingRequiredTables();

        if ($missingTables !== []) {
            if ((bool) $this->option('json')) {
                $this->line((string) json_encode([
                    'generated_at' => now()->toIso8601String(),
                    'orphans' => [
                        'post' => [],
                        'handout' => [],
                    ],
                    'summary' => [
                        'orphan_post_media_rows_count' => 0,
                        'orphan_handout_media_rows_count' => 0,
                        'missing_physical_file_count' => 0,
                        'existing_physical_orphan_file_count' => 0,
                        'physical_file_unknown_count' => 0,
                        'read_only' => true,
                    ],
                    'note' => 'This command is read-only and deletes nothing.',
                    'error' => 'Missing required tables: '.implode(', ', $missingTables),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return self::FAILURE;
            }

            $this->error('Cannot run audit. Missing required tables: '.implode(', ', $missingTables));
            $this->line('note: command is read-only and deletes nothing');

            return self::FAILURE;
        }

        $postOrphans = $this->fetchOrphanRows(Post::class, 'posts');
        $handoutOrphans = $this->fetchOrphanRows(Handout::class, 'handouts');

        $allOrphans = $postOrphans->concat($handoutOrphans);

        $summary = [
            'orphan_post_media_rows_count' => $postOrphans->count(),
            'orphan_handout_media_rows_count' => $handoutOrphans->count(),
            'missing_physical_file_count' => $allOrphans->where('physical_file_exists', 'no')->count(),
            'existing_physical_orphan_file_count' => $allOrphans->where('physical_file_exists', 'yes')->count(),
            'physical_file_unknown_count' => $allOrphans->where('physical_file_exists', 'unknown')->count(),
            'read_only' => true,
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'generated_at' => now()->toIso8601String(),
                'orphans' => [
                    'post' => $postOrphans->values()->all(),
                    'handout' => $handoutOrphans->values()->all(),
                ],
                'summary' => $summary,
                'note' => 'This command is read-only and deletes nothing.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Orphan media audit for posts and handouts (read-only)');
        $this->newLine();

        $this->renderOrphanSection('Post media orphans', $postOrphans);
        $this->newLine();
        $this->renderOrphanSection('Handout media orphans', $handoutOrphans);
        $this->newLine();

        $this->info('Summary');
        $this->line('orphan post media rows count: '.$summary['orphan_post_media_rows_count']);
        $this->line('orphan handout media rows count: '.$summary['orphan_handout_media_rows_count']);
        $this->line('missing physical file count: '.$summary['missing_physical_file_count']);
        $this->line('existing physical orphan file count: '.$summary['existing_physical_orphan_file_count']);
        $this->line('physical file exists unknown count: '.$summary['physical_file_unknown_count']);
        $this->line('note: command is read-only and deletes nothing');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function missingRequiredTables(): array
    {
        /** @var list<string> $required */
        $required = ['media', 'posts', 'handouts'];

        return array_values(array_filter($required, static fn (string $table): bool => ! Schema::hasTable($table)));
    }

    /**
     * @return Collection<int, array{
     *   media_id: int,
     *   model_type: string,
     *   model_id: int|string|null,
     *   collection_name: string,
     *   disk: string,
     *   file_name: string,
     *   size: int,
     *   created_at: string|null,
     *   physical_file_exists: string
     * }>
     */
    private function fetchOrphanRows(string $modelType, string $parentTable): Collection
    {
        return Media::query()
            ->select('media.*')
            ->leftJoin($parentTable, 'media.model_id', '=', $parentTable.'.id')
            ->where('media.model_type', $modelType)
            ->whereNull($parentTable.'.id')
            ->orderBy('media.id')
            ->get()
            ->map(fn (Media $media): array => $this->formatOrphanRow($media));
    }

    /**
     * @return array{
     *   media_id: int,
     *   model_type: string,
     *   model_id: int|string|null,
     *   collection_name: string,
     *   disk: string,
     *   file_name: string,
     *   size: int,
     *   created_at: string|null,
     *   physical_file_exists: string
     * }
     */
    private function formatOrphanRow(Media $media): array
    {
        return [
            'media_id' => (int) $media->getKey(),
            'model_type' => (string) $media->model_type,
            'model_id' => $media->model_id,
            'collection_name' => (string) $media->collection_name,
            'disk' => (string) $media->disk,
            'file_name' => (string) $media->file_name,
            'size' => (int) $media->size,
            'created_at' => $media->created_at?->toDateTimeString(),
            'physical_file_exists' => $this->detectPhysicalFileState($media),
        ];
    }

    private function detectPhysicalFileState(Media $media): string
    {
        $diskName = trim((string) $media->disk);

        if ($diskName === '') {
            return 'unknown';
        }

        try {
            $relativePath = $media->getPathRelativeToRoot();
        } catch (Throwable) {
            return 'unknown';
        }

        if (trim($relativePath) === '') {
            return 'unknown';
        }

        try {
            return Storage::disk($diskName)->exists($relativePath) ? 'yes' : 'no';
        } catch (Throwable) {
            return 'unknown';
        }
    }

    /**
     * @param  Collection<int, array{
     *   media_id: int,
     *   model_type: string,
     *   model_id: int|string|null,
     *   collection_name: string,
     *   disk: string,
     *   file_name: string,
     *   size: int,
     *   created_at: string|null,
     *   physical_file_exists: string
     * }>  $rows
     */
    private function renderOrphanSection(string $title, Collection $rows): void
    {
        $this->info($title.': '.(string) $rows->count());

        if ($rows->isEmpty()) {
            $this->line('none');

            return;
        }

        $this->table([
            'media id',
            'model_type',
            'model_id',
            'collection_name',
            'disk',
            'file_name',
            'size',
            'created_at',
            'physical file exists',
        ], $rows->map(static fn (array $row): array => [
            $row['media_id'],
            $row['model_type'],
            $row['model_id'],
            $row['collection_name'],
            $row['disk'],
            $row['file_name'],
            $row['size'],
            $row['created_at'],
            $row['physical_file_exists'],
        ])->all());
    }
}
