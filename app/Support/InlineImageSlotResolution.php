<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final readonly class InlineImageSlotResolution
{
    /**
     * @param  array<int, Media>  $mediaBySlot
     * @param  array<int, int>  $slotByMediaId
     * @param  Collection<int, Media>  $orderedMedia
     */
    public function __construct(
        private array $mediaBySlot,
        private array $slotByMediaId,
        private Collection $orderedMedia,
    ) {}

    /**
     * @return array<int, Media>
     */
    public function mediaBySlot(): array
    {
        return $this->mediaBySlot;
    }

    /**
     * @return array<int, int>
     */
    public function slotByMediaId(): array
    {
        return $this->slotByMediaId;
    }

    /**
     * @return Collection<int, Media>
     */
    public function orderedMedia(): Collection
    {
        return $this->orderedMedia;
    }

    public function slotFor(Media|int $media): ?int
    {
        $mediaId = $media instanceof Media ? (int) $media->id : $media;

        return $this->slotByMediaId[$mediaId] ?? null;
    }

    /**
     * @return list<int>
     */
    public function occupiedSlots(): array
    {
        $slots = array_keys($this->mediaBySlot);
        sort($slots, SORT_NUMERIC);

        return array_values(array_map(static fn (int|string $slot): int => (int) $slot, $slots));
    }
}
