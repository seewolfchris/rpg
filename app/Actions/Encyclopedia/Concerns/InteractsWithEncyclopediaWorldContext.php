<?php

declare(strict_types=1);

namespace App\Actions\Encyclopedia\Concerns;

use App\Models\EncyclopediaCategory;
use App\Models\EncyclopediaEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

trait InteractsWithEncyclopediaWorldContext
{
    private function lockAndVerifyPublicCategory(EncyclopediaCategory $category): EncyclopediaCategory
    {
        /** @var EncyclopediaCategory $lockedCategory */
        $lockedCategory = EncyclopediaCategory::query()
            ->whereKey((int) $category->id)
            ->where('world_id', (int) $category->world_id)
            ->where('is_public', true)
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedCategory;
    }

    private function lockAndVerifyEntryContext(EncyclopediaEntry $entry): EncyclopediaEntry
    {
        $expectedWorldId = $this->resolveAndValidateEntryWorldId($entry);

        /** @var EncyclopediaEntry $lockedEntry */
        $lockedEntry = EncyclopediaEntry::query()
            ->whereKey((int) $entry->id)
            ->where('encyclopedia_category_id', (int) $entry->encyclopedia_category_id)
            ->whereHas('category', static function (Builder $query) use ($expectedWorldId): void {
                $query->where('world_id', $expectedWorldId);
            })
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedEntry;
    }

    private function lockAndVerifyPendingEntryContext(EncyclopediaEntry $entry): EncyclopediaEntry
    {
        $expectedWorldId = $this->resolveAndValidateEntryWorldId($entry);

        /** @var EncyclopediaEntry $lockedEntry */
        $lockedEntry = EncyclopediaEntry::query()
            ->whereKey((int) $entry->id)
            ->where('status', EncyclopediaEntry::STATUS_PENDING)
            ->where('encyclopedia_category_id', (int) $entry->encyclopedia_category_id)
            ->whereHas('category', static function (Builder $query) use ($expectedWorldId): void {
                $query->where('world_id', $expectedWorldId);
            })
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedEntry;
    }

    private function resolveAndValidateTargetCategory(
        EncyclopediaEntry $entry,
        int $targetCategoryId,
    ): EncyclopediaCategory {
        /** @var EncyclopediaCategory $lockedCurrentCategory */
        $lockedCurrentCategory = EncyclopediaCategory::query()
            ->whereKey((int) $entry->encyclopedia_category_id)
            ->lockForUpdate()
            ->firstOrFail();

        /** @var EncyclopediaCategory $lockedTargetCategory */
        $lockedTargetCategory = EncyclopediaCategory::query()
            ->whereKey($targetCategoryId)
            ->where('world_id', (int) $lockedCurrentCategory->world_id)
            ->where('is_public', true)
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedTargetCategory;
    }

    /**
     * @param  array<string, mixed>  $transition
     */
    private function persistReviewTransition(EncyclopediaEntry $entry, User $reviewer, array $transition): void
    {
        $entry->update(array_merge([
            'updated_by' => (int) $reviewer->id,
            'reviewed_by' => (int) $reviewer->id,
            'reviewed_at' => now(),
        ], $transition));
    }

    private function resolveAndValidateEntryWorldId(EncyclopediaEntry $entry): int
    {
        $categoryId = (int) $entry->encyclopedia_category_id;

        /** @var int|null $worldId */
        $worldId = EncyclopediaCategory::query()
            ->whereKey($categoryId)
            ->value('world_id');

        if (! is_int($worldId) || $worldId <= 0) {
            throw (new ModelNotFoundException)->setModel(EncyclopediaCategory::class, [$categoryId]);
        }

        return $worldId;
    }
}
