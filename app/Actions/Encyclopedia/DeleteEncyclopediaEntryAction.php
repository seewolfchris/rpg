<?php

declare(strict_types=1);

namespace App\Actions\Encyclopedia;

use App\Models\EncyclopediaCategory;
use App\Models\EncyclopediaEntry;
use App\Models\World;
use Illuminate\Database\DatabaseManager;

final class DeleteEncyclopediaEntryAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function execute(World $world, EncyclopediaCategory $category, EncyclopediaEntry $entry): void
    {
        $this->db->transaction(function () use ($world, $category, $entry): void {
            $lockedEntry = $this->lockAndVerifyContext($world, $category, $entry);

            $this->persistDeletion($lockedEntry);
        }, 3);
    }

    private function lockAndVerifyContext(
        World $world,
        EncyclopediaCategory $category,
        EncyclopediaEntry $entry,
    ): EncyclopediaEntry {
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

        return $lockedEntry;
    }

    private function persistDeletion(EncyclopediaEntry $entry): void
    {
        $entry->delete();
    }
}
