<?php

namespace App\Domain\Handout;

use App\Models\Handout;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class HandoutMediaService
{
    public function attachPrimaryFile(Handout $handout, UploadedFile $file): Media
    {
        /** @var Media $media */
        $media = $handout
            ->addMedia($file)
            ->toMediaCollection(Handout::HANDOUT_FILE_COLLECTION);

        return $media;
    }

    public function replacePrimaryFile(Handout $handout, UploadedFile $file): Media
    {
        $existingMedia = $handout->media()
            ->where('collection_name', Handout::HANDOUT_FILE_COLLECTION)
            ->get();

        $newMedia = $this->attachPrimaryFile($handout, $file);

        foreach ($existingMedia as $mediaItem) {
            if ((int) $mediaItem->id === (int) $newMedia->id) {
                continue;
            }

            $mediaItem->delete();
        }

        $remainingMedia = $handout->media()
            ->where('collection_name', Handout::HANDOUT_FILE_COLLECTION)
            ->orderByDesc('id')
            ->get();

        $keepFirst = true;
        foreach ($remainingMedia as $mediaItem) {
            if ($keepFirst) {
                $keepFirst = false;

                continue;
            }

            $mediaItem->delete();
        }

        $resolvedMedia = $this->resolvePrimaryMediaForDelivery($handout);

        if (! $resolvedMedia instanceof Media) {
            throw new \RuntimeException('Die neue Handout-Datei konnte nicht als Primärdatei aufgelöst werden.');
        }

        return $resolvedMedia;
    }

    public function resolvePrimaryMediaForDelivery(Handout $handout): ?Media
    {
        $media = $handout->getFirstMedia(Handout::HANDOUT_FILE_COLLECTION);

        if (! $media instanceof Media) {
            return null;
        }

        if ($media->model_type !== Handout::class) {
            return null;
        }

        if ((int) $media->model_id !== (int) $handout->id) {
            return null;
        }

        if ((string) $media->collection_name !== Handout::HANDOUT_FILE_COLLECTION) {
            return null;
        }

        if ((string) $media->disk !== 'local') {
            return null;
        }

        return $media;
    }
}
