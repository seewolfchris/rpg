<?php

declare(strict_types=1);

namespace App\Domain\Post;

use App\Models\Post;
use Illuminate\Http\UploadedFile;

final class PostImmersiveImageService
{
    /**
     * @param  list<UploadedFile>  $files
     */
    public function attachImmersiveImages(Post $post, array $files): int
    {
        $attachedCount = 0;

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $post
                ->addMedia($file)
                ->toMediaCollection(Post::IMMERSIVE_IMAGES_COLLECTION);

            $attachedCount++;
        }

        return $attachedCount;
    }

    /**
     * @param  list<int>  $mediaIds
     */
    public function removeImmersiveImagesById(Post $post, array $mediaIds): int
    {
        $normalizedIds = $this->normalizeMediaIds($mediaIds);

        if ($normalizedIds === []) {
            return 0;
        }

        $mediaItems = $post->media()
            ->where('collection_name', Post::IMMERSIVE_IMAGES_COLLECTION)
            ->whereIn('id', $normalizedIds)
            ->get();

        foreach ($mediaItems as $mediaItem) {
            $mediaItem->delete();
        }

        return $mediaItems->count();
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

        $normalized = array_values(array_unique($normalized));

        return $normalized;
    }
}

