<?php

declare(strict_types=1);

namespace App\Actions\Encyclopedia;

use App\Models\EncyclopediaCategory;
use App\Models\EncyclopediaEntry;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Throwable;

final class UpdateEncyclopediaEntryAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $mediaFiles
     * @param  list<int>  $removeMediaIds
     */
    public function execute(
        World $world,
        EncyclopediaCategory $category,
        EncyclopediaEntry $entry,
        User $actor,
        array $data,
        array $mediaFiles = [],
        array $removeMediaIds = [],
    ): void {
        $this->db->transaction(function () use ($world, $category, $entry, $actor, $data): void {
            [$lockedCategory, $lockedEntry] = $this->lockAndVerifyContext($world, $category, $entry);
            $normalizedPublishedAt = $this->resolveAndValidatePublishedAt(
                status: (string) ($data['status'] ?? EncyclopediaEntry::STATUS_DRAFT),
                publishedAt: $data['published_at'] ?? null,
            );

            $this->persistEntry($lockedCategory, $lockedEntry, $actor, $data, $normalizedPublishedAt);
        }, 3);

        $this->mutateMediaAfterCommit($entry, $mediaFiles, $removeMediaIds);
        $entry->refresh();
    }

    /**
     * @return array{0: EncyclopediaCategory, 1: EncyclopediaEntry}
     */
    private function lockAndVerifyContext(
        World $world,
        EncyclopediaCategory $category,
        EncyclopediaEntry $entry,
    ): array {
        /** @var World $lockedWorld */
        $lockedWorld = World::query()
            ->whereKey((int) $world->id)
            ->lockForUpdate()
            ->firstOrFail();

        /** @var EncyclopediaCategory $lockedCategory */
        $lockedCategory = EncyclopediaCategory::query()
            ->whereKey((int) $category->id)
            ->where('world_id', (int) $lockedWorld->id)
            ->lockForUpdate()
            ->firstOrFail();

        /** @var EncyclopediaEntry $lockedEntry */
        $lockedEntry = EncyclopediaEntry::query()
            ->whereKey((int) $entry->id)
            ->where('encyclopedia_category_id', (int) $lockedCategory->id)
            ->lockForUpdate()
            ->firstOrFail();

        return [$lockedCategory, $lockedEntry];
    }

    private function resolveAndValidatePublishedAt(string $status, mixed $publishedAt): Carbon|string|null
    {
        if ($status !== EncyclopediaEntry::STATUS_PUBLISHED) {
            return null;
        }

        if ($publishedAt === null || $publishedAt === '') {
            return now();
        }

        if ($publishedAt instanceof Carbon) {
            return $publishedAt;
        }

        return (string) $publishedAt;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistEntry(
        EncyclopediaCategory $category,
        EncyclopediaEntry $entry,
        User $actor,
        array $data,
        Carbon|string|null $publishedAt,
    ): void {
        $entry->update(array_merge($data, [
            'encyclopedia_category_id' => (int) $category->id,
            'updated_by' => (int) $actor->id,
            'published_at' => $publishedAt,
        ]));
    }

    /**
     * @param  list<UploadedFile>  $mediaFiles
     * @param  list<int>  $removeMediaIds
     */
    private function mutateMediaAfterCommit(EncyclopediaEntry $entry, array $mediaFiles, array $removeMediaIds): void
    {
        try {
            $this->removeMediaById($entry, $removeMediaIds);
            $this->attachMediaFiles($entry, $mediaFiles);
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }

    /**
     * @param  list<int>  $removeMediaIds
     */
    private function removeMediaById(EncyclopediaEntry $entry, array $removeMediaIds): void
    {
        $normalizedIds = [];

        foreach ($removeMediaIds as $removeMediaId) {
            $id = is_numeric($removeMediaId) ? (int) $removeMediaId : 0;

            if ($id <= 0) {
                continue;
            }

            $normalizedIds[] = $id;
        }

        $normalizedIds = array_values(array_unique($normalizedIds));

        if ($normalizedIds === []) {
            return;
        }

        $mediaItems = $entry->media()
            ->where('collection_name', EncyclopediaEntry::ENTRY_MEDIA_COLLECTION)
            ->whereIn('id', $normalizedIds)
            ->get();

        foreach ($mediaItems as $mediaItem) {
            $mediaItem->delete();
        }
    }

    /**
     * @param  list<UploadedFile>  $mediaFiles
     */
    private function attachMediaFiles(EncyclopediaEntry $entry, array $mediaFiles): void
    {
        foreach ($mediaFiles as $mediaFile) {
            if (! $mediaFile instanceof UploadedFile) {
                continue;
            }

            $entry
                ->addMedia($mediaFile)
                ->toMediaCollection(EncyclopediaEntry::ENTRY_MEDIA_COLLECTION);
        }
    }
}
