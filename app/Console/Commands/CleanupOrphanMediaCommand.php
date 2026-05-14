<?php

namespace App\Console\Commands;

use App\Models\Handout;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class CleanupOrphanMediaCommand extends Command
{
    protected $signature = 'rpg:cleanup-orphan-media
        {--dry-run : Simulate cleanup without deleting anything}
        {--execute : Execute cleanup and delete orphan media}
        {--json : Output the cleanup result as JSON}';

    protected $description = 'Safely cleanup orphan media rows for posts and handouts.';

    public function handle(): int
    {
        $mode = $this->resolveMode();

        if ($mode === null) {
            return self::FAILURE;
        }

        $missingTables = $this->missingRequiredTables();

        if ($missingTables !== []) {
            return $this->failMissingTables($missingTables, $mode);
        }

        $postOrphans = $this->fetchOrphanRows(Post::class, 'posts');
        $handoutOrphans = $this->fetchOrphanRows(Handout::class, 'handouts');
        $allOrphans = $postOrphans->concat($handoutOrphans)->values();

        $deletedCount = 0;
        $failedCount = 0;
        $skippedCount = 0;
        $actions = [];

        foreach ($allOrphans as $row) {
            $mediaId = (int) $row['media_id'];

            if ($mode === 'dry-run') {
                $skippedCount++;
                $actions[] = [
                    'media_id' => $mediaId,
                    'model_type' => $row['model_type'],
                    'model_id' => $row['model_id'],
                    'status' => 'would_delete',
                ];

                continue;
            }

            $media = Media::query()->find($mediaId);

            if (! $media instanceof Media) {
                $skippedCount++;
                $actions[] = [
                    'media_id' => $mediaId,
                    'model_type' => $row['model_type'],
                    'model_id' => $row['model_id'],
                    'status' => 'skipped_not_found',
                ];

                continue;
            }

            if (! $this->isAllowedModelType((string) $media->model_type)) {
                $skippedCount++;
                $actions[] = [
                    'media_id' => $mediaId,
                    'model_type' => (string) $media->model_type,
                    'model_id' => $media->model_id,
                    'status' => 'skipped_model_type_not_allowed',
                ];

                continue;
            }

            if (! $this->isOrphan($media)) {
                $skippedCount++;
                $actions[] = [
                    'media_id' => $mediaId,
                    'model_type' => (string) $media->model_type,
                    'model_id' => $media->model_id,
                    'status' => 'skipped_parent_exists',
                ];

                continue;
            }

            try {
                // Important: delete via Spatie Media model to keep package cleanup behavior.
                $media->delete();
                $deletedCount++;
                $actions[] = [
                    'media_id' => $mediaId,
                    'model_type' => (string) $media->model_type,
                    'model_id' => $media->model_id,
                    'status' => 'deleted',
                ];
            } catch (Throwable $exception) {
                $failedCount++;
                $actions[] = [
                    'media_id' => $mediaId,
                    'model_type' => (string) $media->model_type,
                    'model_id' => $media->model_id,
                    'status' => 'failed',
                    'error' => $exception->getMessage(),
                ];
            }
        }

        $summary = [
            'found_orphan_post_media_count' => $postOrphans->count(),
            'found_orphan_handout_media_count' => $handoutOrphans->count(),
            'deleted_count' => $deletedCount,
            'failed_count' => $failedCount,
            'skipped_count' => $skippedCount,
            'mode' => $mode,
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'generated_at' => now()->toIso8601String(),
                'summary' => $summary,
                'orphans' => [
                    'post' => $postOrphans->values()->all(),
                    'handout' => $handoutOrphans->values()->all(),
                ],
                'actions' => $actions,
                'note' => 'Cleanup only targets orphan post/handout media and uses Spatie Media model delete.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $failedCount > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Orphan media cleanup');
        $this->line('mode: '.$mode);
        $this->newLine();

        if ($actions === []) {
            $this->line('No orphan media rows found.');
        } else {
            foreach ($actions as $action) {
                if ($action['status'] === 'would_delete') {
                    $this->line(sprintf(
                        '[dry-run] would delete media id=%d (%s:%s)',
                        (int) $action['media_id'],
                        (string) $action['model_type'],
                        (string) $action['model_id']
                    ));

                    continue;
                }

                if ($action['status'] === 'deleted') {
                    $this->line(sprintf(
                        '[execute] deleted media id=%d (%s:%s)',
                        (int) $action['media_id'],
                        (string) $action['model_type'],
                        (string) $action['model_id']
                    ));

                    continue;
                }

                $this->line(sprintf(
                    '[execute] %s media id=%d (%s:%s)',
                    (string) $action['status'],
                    (int) $action['media_id'],
                    (string) $action['model_type'],
                    (string) $action['model_id']
                ));
            }
        }

        $this->newLine();
        $this->info('Summary');
        $this->line('found orphan post media count: '.$summary['found_orphan_post_media_count']);
        $this->line('found orphan handout media count: '.$summary['found_orphan_handout_media_count']);
        $this->line('deleted count: '.$summary['deleted_count']);
        $this->line('failed count: '.$summary['failed_count']);
        $this->line('skipped count: '.$summary['skipped_count']);
        $this->line('mode: '.$summary['mode']);

        return $failedCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return Collection<int, array{
     *   media_id: int,
     *   model_type: string,
     *   model_id: int|string,
     *   collection_name: string,
     *   disk: string,
     *   file_name: string
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
            ->map(static fn (Media $media): array => [
                'media_id' => (int) $media->id,
                'model_type' => (string) $media->model_type,
                'model_id' => $media->model_id,
                'collection_name' => (string) $media->collection_name,
                'disk' => (string) $media->disk,
                'file_name' => (string) $media->file_name,
            ]);
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

    private function isAllowedModelType(string $modelType): bool
    {
        return in_array($modelType, [Post::class, Handout::class], true);
    }

    private function isOrphan(Media $media): bool
    {
        $modelType = (string) $media->model_type;
        $modelId = $media->model_id;

        if ($modelType === Post::class) {
            return ! Post::query()->whereKey($modelId)->exists();
        }

        if ($modelType === Handout::class) {
            return ! Handout::query()->whereKey($modelId)->exists();
        }

        return false;
    }

    private function resolveMode(): ?string
    {
        $execute = (bool) $this->option('execute');
        $dryRun = (bool) $this->option('dry-run');

        if ($execute && $dryRun) {
            if ((bool) $this->option('json')) {
                $this->line((string) json_encode([
                    'error' => 'Use either --dry-run or --execute, not both.',
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $this->error('Use either --dry-run or --execute, not both.');
            }

            return null;
        }

        if ($execute) {
            return 'execute';
        }

        return 'dry-run';
    }

    /**
     * @param  list<string>  $missingTables
     */
    private function failMissingTables(array $missingTables, string $mode): int
    {
        $message = 'Missing required tables: '.implode(', ', $missingTables);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'generated_at' => now()->toIso8601String(),
                'summary' => [
                    'found_orphan_post_media_count' => 0,
                    'found_orphan_handout_media_count' => 0,
                    'deleted_count' => 0,
                    'failed_count' => 0,
                    'skipped_count' => 0,
                    'mode' => $mode,
                ],
                'orphans' => [
                    'post' => [],
                    'handout' => [],
                ],
                'actions' => [],
                'error' => $message,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->error($message);
            $this->line('mode: '.$mode);
        }

        return self::FAILURE;
    }
}
