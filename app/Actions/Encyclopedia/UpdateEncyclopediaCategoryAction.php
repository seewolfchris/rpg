<?php

declare(strict_types=1);

namespace App\Actions\Encyclopedia;

use App\Models\EncyclopediaCategory;
use App\Models\World;
use Illuminate\Database\DatabaseManager;

final class UpdateEncyclopediaCategoryAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(World $world, EncyclopediaCategory $category, array $data): void
    {
        $this->db->transaction(function () use ($world, $category, $data): void {
            $lockedCategory = $this->lockAndVerifyContext($world, $category);

            $this->persistCategory($lockedCategory, $data);
        }, 3);

        $category->refresh();
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistCategory(EncyclopediaCategory $category, array $data): void
    {
        $category->update($data);
    }
}
