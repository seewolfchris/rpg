<?php

declare(strict_types=1);

namespace App\Actions\Encyclopedia;

use App\Models\EncyclopediaCategory;
use App\Models\World;
use Illuminate\Database\DatabaseManager;

final class DeleteEncyclopediaCategoryAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function execute(World $world, EncyclopediaCategory $category): void
    {
        $this->db->transaction(function () use ($world, $category): void {
            $lockedCategory = $this->lockAndVerifyContext($world, $category);

            $this->persistDeletion($lockedCategory);
        }, 3);
    }

    private function lockAndVerifyContext(World $world, EncyclopediaCategory $category): EncyclopediaCategory
    {
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

        return $lockedCategory;
    }

    private function persistDeletion(EncyclopediaCategory $category): void
    {
        $category->delete();
    }
}
