<?php

declare(strict_types=1);

namespace App\Domain\Scene;

use App\Domain\Media\InlineImageMediaMutationException;
use App\Domain\Media\InlineImageMediaMutationResult;
use App\Models\Scene;
use App\Support\InlineImageSlotResolver;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

final class SceneContentImageService
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly InlineImageSlotResolver $inlineImageSlotResolver,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     * @param  list<int>  $removeMediaIds
     */
    public function mutateContentImages(Scene $scene, array $files = [], array $removeMediaIds = []): InlineImageMediaMutationResult
    {
        $files = $this->normalizeUploadedFiles($files);
        $removeMediaIds = $this->normalizeMediaIds($removeMediaIds);
        $createdMedia = [];

        try {
            return $this->db->transaction(function () use ($scene, $files, $removeMediaIds, &$createdMedia): InlineImageMediaMutationResult {
                /** @var Scene $lockedScene */
                $lockedScene = Scene::query()
                    ->whereKey((int) $scene->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $currentMedia = $this->lockedContentMedia($lockedScene);
                $currentMediaIds = $currentMedia
                    ->map(static fn (Media $media): int => (int) $media->id)
                    ->all();

                $invalidRemoveIds = array_values(array_diff($removeMediaIds, $currentMediaIds));
                if ($invalidRemoveIds !== []) {
                    throw InlineImageMediaMutationException::invalidRemoval();
                }

                $remainingMedia = $currentMedia
                    ->reject(static fn (Media $media): bool => in_array((int) $media->id, $removeMediaIds, true))
                    ->values();

                if (($remainingMedia->count() + count($files)) > InlineImageSlotResolver::MAX_SLOT) {
                    throw InlineImageMediaMutationException::imageLimitExceeded();
                }

                $freeSlots = $this->inlineImageSlotResolver->freeSlots(
                    $this->inlineImageSlotResolver->resolve($remainingMedia)
                );
                $attachedCount = 0;

                foreach ($files as $file) {
                    $slot = array_shift($freeSlots);

                    if (! is_int($slot)) {
                        throw InlineImageMediaMutationException::imageLimitExceeded();
                    }

                    /** @var Media $media */
                    $media = $lockedScene
                        ->addMedia($file)
                        ->withCustomProperties(['slot' => $slot])
                        ->toMediaCollection(Scene::CONTENT_IMAGES_COLLECTION);

                    $createdMedia[] = $media;
                    $attachedCount++;
                }

                $removedCount = 0;
                foreach ($currentMedia as $media) {
                    if (! in_array((int) $media->id, $removeMediaIds, true)) {
                        continue;
                    }

                    $media->delete();
                    $removedCount++;
                }

                return new InlineImageMediaMutationResult(
                    attachedCount: $attachedCount,
                    removedCount: $removedCount,
                );
            }, 3);
        } catch (InlineImageMediaMutationException $exception) {
            $this->cleanupCreatedMediaFiles($createdMedia);

            throw $exception;
        } catch (Throwable $throwable) {
            $this->cleanupCreatedMediaFiles($createdMedia);

            throw InlineImageMediaMutationException::uploadFailed($throwable);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, Media>
     */
    private function lockedContentMedia(Scene $scene): \Illuminate\Support\Collection
    {
        return $scene->media()
            ->where('collection_name', Scene::CONTENT_IMAGES_COLLECTION)
            ->orderBy('order_column')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<mixed>  $files
     * @return list<UploadedFile>
     */
    private function normalizeUploadedFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $normalized[] = $file;
        }

        return $normalized;
    }

    /**
     * @param  array<mixed>  $mediaIds
     * @return list<int>
     */
    private function normalizeMediaIds(array $mediaIds): array
    {
        $normalized = [];

        foreach ($mediaIds as $mediaId) {
            $id = is_numeric($mediaId) ? (int) $mediaId : 0;

            if ($id <= 0) {
                continue;
            }

            $normalized[] = $id;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  list<Media>  $mediaItems
     */
    private function cleanupCreatedMediaFiles(array $mediaItems): void
    {
        foreach ($mediaItems as $media) {
            try {
                if ($media->exists) {
                    $media->delete();

                    continue;
                }

                $path = $media->getPathRelativeToRoot();
                if ($path !== '') {
                    Storage::disk((string) $media->disk)->delete($path);
                }
            } catch (Throwable $throwable) {
                report($throwable);
            }
        }
    }
}
