<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class InlineImageSlotResolver
{
    public const MIN_SLOT = 1;

    public const MAX_SLOT = 4;

    /**
     * @param  iterable<int, mixed>  $mediaItems
     */
    public function resolve(iterable $mediaItems): InlineImageSlotResolution
    {
        $orderedMedia = $this->orderedMedia($mediaItems);
        $mediaBySlot = [];
        $slotByMediaId = [];
        $unslottedMedia = [];

        foreach ($orderedMedia as $media) {
            $slot = $this->validPersistedSlot($media);

            if ($slot !== null && ! array_key_exists($slot, $mediaBySlot)) {
                $mediaBySlot[$slot] = $media;
                $slotByMediaId[(int) $media->id] = $slot;

                continue;
            }

            $unslottedMedia[] = $media;
        }

        foreach ($unslottedMedia as $media) {
            $slot = $this->firstFreeSlot($mediaBySlot);

            if ($slot === null) {
                continue;
            }

            $mediaBySlot[$slot] = $media;
            $slotByMediaId[(int) $media->id] = $slot;
        }

        ksort($mediaBySlot, SORT_NUMERIC);
        ksort($slotByMediaId, SORT_NUMERIC);

        return new InlineImageSlotResolution(
            mediaBySlot: $mediaBySlot,
            slotByMediaId: $slotByMediaId,
            orderedMedia: $orderedMedia,
        );
    }

    /**
     * @param  iterable<int, mixed>  $mediaItems
     * @return Collection<int, Media>
     */
    public function orderedMedia(iterable $mediaItems): Collection
    {
        return collect($mediaItems)
            ->filter(static fn (mixed $media): bool => $media instanceof Media)
            ->sortBy([
                ['order_column', 'asc'],
                ['id', 'asc'],
            ])
            ->values();
    }

    /**
     * @return list<int>
     */
    public function freeSlots(InlineImageSlotResolution $resolution): array
    {
        $occupiedSlots = $resolution->occupiedSlots();
        $freeSlots = [];

        for ($slot = self::MIN_SLOT; $slot <= self::MAX_SLOT; $slot++) {
            if (in_array($slot, $occupiedSlots, true)) {
                continue;
            }

            $freeSlots[] = $slot;
        }

        return $freeSlots;
    }

    public function validPersistedSlot(Media $media): ?int
    {
        $rawSlot = $media->getCustomProperty('slot');

        if (! is_numeric($rawSlot)) {
            return null;
        }

        $slot = (int) $rawSlot;

        if ($slot < self::MIN_SLOT || $slot > self::MAX_SLOT) {
            return null;
        }

        return $slot;
    }

    /**
     * @param  array<int, Media>  $mediaBySlot
     */
    private function firstFreeSlot(array $mediaBySlot): ?int
    {
        for ($slot = self::MIN_SLOT; $slot <= self::MAX_SLOT; $slot++) {
            if (! array_key_exists($slot, $mediaBySlot)) {
                return $slot;
            }
        }

        return null;
    }
}
